<?php

namespace Base\Ledger\Bank;

use Base\Ledger\Entity\BankAccount;
use Base\Ledger\Entity\BankConnection;
use Base\Ledger\Enum\ConsentState;
use Base\Ledger\Exception\BankException;
use Doctrine\ORM\EntityManagerInterface;
use Omnibank\Model\Account as BankAccountModel;
use Omnibank\Model\Connection;
use Omnibank\Request\FetchBalances;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Brings a bank account's new lines in through its connection's gateway:
 * since the last sync, minus a few days (banks book late; lines already
 * there are matched on their id), with the bank's balance when the gateway
 * gives one. A consent the bank wants renewed is recorded on the connection
 * - the back office then shows "Renouveler" - and the sync stops there.
 */
class Sync
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connections $connections,
        private readonly Importer $importer,
        #[Autowire('%ledger.bank.sync_overlap_days%')] private readonly int $overlapDays = 5,
    ) {
    }

    /** @return int how many new lines */
    public function sync(BankAccount $account, ?\DateTimeImmutable $until = null): int
    {
        $connection = $account->getConnection();
        $gateway = $this->connections->gateway($connection->getGateway());
        $omnibank = $this->connections->open($connection);
        $now = new \DateTimeImmutable();

        if ($connection->needsRenewal($now) || !$omnibank->consent->isActive($now)) {
            $this->markRenewal($connection, $omnibank, 'The bank wants its consent given again.');

            throw new BankException(sprintf('%s: the bank\'s consent needs renewing.', $connection));
        }

        $since = $account->getLastSyncedAt()?->modify(sprintf('-%d days', $this->overlapDays))->setTime(0, 0);
        try {
            $model = $this->model($account, $omnibank);
            $created = $this->importer->import($account, $gateway->transactions($omnibank, $model, $since, $until));

            if ($gateway->supports(FetchBalances::class)) {
                $balances = $gateway->balances($omnibank, $model);
                $booked = array_values(array_filter($balances, fn ($balance) => 'booked' === $balance->type)) ?: $balances;
                if ([] !== $booked) {
                    $account->setBankBalance(end($booked)->amount->amount, end($booked)->at);
                }
            }
        } catch (BankException $e) {
            $connection->setLastError($e->getMessage());
            $this->entityManager->flush();

            throw $e;
        } catch (\Throwable $e) {
            $connection->setLastError($e->getMessage());
            if (self::isConsentError($e)) {
                $connection->setConsentStatus(ConsentState::NEEDS_RENEWAL);
            }
            $this->entityManager->flush();

            throw new BankException(sprintf('%s: %s', $connection, $e->getMessage()), 0, $e);
        }

        $account->setLastSyncedAt($now);
        $connection->setLastError(null);
        $this->entityManager->flush();

        return $created;
    }

    /**
     * Every account of a connection (a webhook said something changed);
     * returns how many new lines.
     */
    public function syncConnection(BankConnection $connection): int
    {
        $created = 0;
        foreach ($connection->getAccounts() as $account) {
            $created += $this->sync($account);
        }

        return $created;
    }

    /** The gateway's own description of the account: what transactions() is asked with. */
    private function model(BankAccount $account, Connection $omnibank): BankAccountModel
    {
        $gateway = $this->connections->gateway($account->getConnection()->getGateway());
        foreach ($gateway->accounts($omnibank) as $candidate) {
            if ($candidate->id === $account->getExternalId()) {
                return $candidate;
            }
        }

        throw new BankException(sprintf('The bank no longer lists the account "%s" (%s).', $account->getName(), $account->getExternalId()));
    }

    private function markRenewal(BankConnection $connection, Connection $omnibank, string $message): void
    {
        $connection->setConsentStatus(ConsentState::REVOKED === ConsentState::of($omnibank->consent->status) ? ConsentState::REVOKED : ConsentState::NEEDS_RENEWAL);
        $connection->setLastError($message);
        $this->entityManager->flush();
    }

    /** A provider's refusal that a new consent would cure (expired, SCA, 401/403). */
    private static function isConsentError(\Throwable $e): bool
    {
        return (bool) preg_match('/consent|expired|reauth|sca|unauthori[sz]ed|forbidden|\b40[13]\b/i', $e->getMessage());
    }
}
