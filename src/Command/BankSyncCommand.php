<?php

namespace Base\Ledger\Command;

use Base\Ledger\Bank\StatementUpload;
use Base\Ledger\Bank\Sync;
use Base\Ledger\Exception\LedgerException;
use Base\Ledger\Reconciliation\Reconciler;
use Base\Ledger\Repository\BankAccountRepository;
use Base\Ledger\Repository\BookRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Brings the bank accounts' new lines in (all books, or one) and reconciles
 * what is sure. Accounts fed by statement files are skipped: they are
 * uploaded. A connection whose consent needs renewing is reported and
 * skipped. Suggested cron: a few times a day.
 */
#[AsCommand(name: 'ledger:bank:sync', description: 'Import the bank accounts\' new lines and reconcile what is sure')]
class BankSyncCommand extends Command
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly BankAccountRepository $bankAccounts,
        private readonly Sync $sync,
        private readonly Reconciler $reconciler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('book', InputArgument::OPTIONAL, 'Only this book: its id, SIREN or name')
            ->addOption('no-reconcile', null, InputOption::VALUE_NONE, 'Import only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $book = null;
        if (null !== $input->getArgument('book')) {
            $book = $this->books->resolve((string) $input->getArgument('book'));
            if (null === $book) {
                $io->error(sprintf('No book "%s".', $input->getArgument('book')));

                return Command::FAILURE;
            }
        }

        $failed = 0;
        foreach ($this->bankAccounts->forBook($book) as $account) {
            if (StatementUpload::GATEWAY === $account->getConnection()->getGateway()) {
                continue;
            }
            try {
                $created = $this->sync->sync($account);
                $reconciled = $input->getOption('no-reconcile') ? 0 : $this->reconciler->autoReconcile($account);
                $io->writeln(sprintf('%s / %s: %d new line(s), %d reconciled.', $account->getBook()->getName(), $account->getName(), $created, $reconciled));
            } catch (LedgerException $e) {
                ++$failed;
                $io->warning(sprintf('%s / %s: %s', $account->getBook()->getName(), $account->getName(), $e->getMessage()));
            }
        }

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }
}
