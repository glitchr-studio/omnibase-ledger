<?php

namespace Base\Ledger\Command;

use Base\Ledger\Export\FecWriter;
use Base\Ledger\Repository\BookRepository;
use Base\Ledger\Repository\FiscalYearRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Writes a fiscal year's FEC, <SIREN>FEC<closing date>.txt, into a directory (the current one by default). */
#[AsCommand(name: 'ledger:fec:export', description: 'Write a fiscal year\'s FEC (fichier des écritures comptables)')]
class FecExportCommand extends Command
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly FiscalYearRepository $fiscalYears,
        private readonly FecWriter $writer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('book', InputArgument::REQUIRED, 'The book: its id, SIREN or name')
            ->addArgument('year', InputArgument::REQUIRED, 'The fiscal year, by the year it closes in (2026)')
            ->addOption('dir', 'd', InputOption::VALUE_REQUIRED, 'Where to write the file', '.')
            ->addOption('stdout', null, InputOption::VALUE_NONE, 'Print it instead');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $book = $this->books->resolve((string) $input->getArgument('book'));
        if (null === $book) {
            $io->error(sprintf('No book "%s".', $input->getArgument('book')));

            return Command::FAILURE;
        }
        if (!ctype_digit((string) $input->getArgument('year'))) {
            $io->error('The year is the one the fiscal year closes in: 2026.');

            return Command::INVALID;
        }

        $year = $this->fiscalYears->forYear($book, (int) $input->getArgument('year'));
        $fec = $this->writer->write($book, $year);
        if ($input->getOption('stdout')) {
            $output->write($fec, false, OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        }

        $path = rtrim((string) $input->getOption('dir'), '/').'/'.FecWriter::filename($book, $year);
        if (false === file_put_contents($path, $fec)) {
            $io->error(sprintf('Could not write %s.', $path));

            return Command::FAILURE;
        }
        $io->success(sprintf('%s: %d line(s) from %s to %s.', $path, substr_count($fec, "\r\n") - 1, $year->getStart()->format('d/m/Y'), $year->getEnd()->format('d/m/Y')));

        return Command::SUCCESS;
    }
}
