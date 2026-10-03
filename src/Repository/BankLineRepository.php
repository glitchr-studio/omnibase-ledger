<?php

namespace Base\Ledger\Repository;

use Base\Ledger\Entity\BankAccount;
use Base\Ledger\Entity\BankLine;
use Base\Ledger\Enum\BankLineStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankLine> */
class BankLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankLine::class);
    }

    public function findOneByExternalId(BankAccount $account, string $externalId): ?BankLine
    {
        return $this->findOneBy(['bankAccount' => $account, 'externalId' => $externalId]);
    }

    /** @return list<BankLine> the lines still to reconcile (unmatched or suggested), oldest first */
    public function pending(BankAccount $account): array
    {
        return $this->findBy(['bankAccount' => $account, 'status' => [BankLineStatus::UNMATCHED, BankLineStatus::SUGGESTED]], ['bookedOn' => 'ASC', 'id' => 'ASC']);
    }

    /** @return list<BankLine> a bank account's lines, newest first */
    public function latest(?BankAccount $account = null, int $limit = 50, ?BankLineStatus $status = null): array
    {
        $criteria = array_filter(['bankAccount' => $account, 'status' => $status], fn ($value) => null !== $value);

        return $this->findBy($criteria, ['bookedOn' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /** @return array<string, int> status value => count, for one bank account */
    public function countByStatus(BankAccount $account): array
    {
        $rows = $this->createQueryBuilder('l')->select('l.status AS status, COUNT(l.id) AS n')
            ->andWhere('l.bankAccount = :account')->setParameter('account', $account)
            ->groupBy('l.status')
            ->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status'] instanceof BankLineStatus ? $row['status']->value : (string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }
}
