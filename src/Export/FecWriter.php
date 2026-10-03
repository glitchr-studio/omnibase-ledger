<?php

namespace Base\Ledger\Export;

use Base\Ledger\Entity\Book;
use Base\Ledger\Entity\Entry;
use Base\Ledger\Entity\FiscalYear;
use Base\Ledger\Repository\AccountRepository;
use Base\Ledger\Repository\EntryRepository;

/**
 * The Fichier des Écritures Comptables (article A47 A-1 du LPF) of a fiscal
 * year: one row per line of every validated entry, the 18 columns in their
 * order, tab-separated, CRLF, dates as YYYYMMDD, amounts with a decimal
 * comma and no thousands separator. A line on a party's auxiliary account
 * (411U3) is filed under its general account (411) with the auxiliary
 * account and the party's name in CompAuxNum and CompAuxLib.
 *
 * The file is named <SIREN>FEC<closing date YYYYMMDD>.txt (filename()).
 */
class FecWriter
{
    public const COLUMNS = ['JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate', 'CompteNum', 'CompteLib', 'CompAuxNum', 'CompAuxLib', 'PieceRef', 'PieceDate', 'EcritureLib', 'Debit', 'Credit', 'EcritureLet', 'DateLet', 'ValidDate', 'Montantdevise', 'Idevise'];

    public function __construct(
        private readonly EntryRepository $entries,
        private readonly AccountRepository $accounts,
    ) {
    }

    public function write(Book $book, FiscalYear $year): string
    {
        return $this->render($book, $this->entries->forPeriod($book, $year->getStart(), $year->getEnd(), true));
    }

    public static function filename(Book $book, FiscalYear $year): string
    {
        return ($book->getSiren() ?? '000000000').'FEC'.$year->getEnd()->format('Ymd').'.txt';
    }

    /** @param iterable<Entry> $entries */
    public function render(Book $book, iterable $entries): string
    {
        $labels = [];
        foreach ($this->accounts->chart($book) as $account) {
            $labels[$account->getNumber()] = $account->getLabel();
        }

        $rows = [implode("\t", self::COLUMNS)];
        foreach ($entries as $entry) {
            if (!$entry->isValidated()) {
                continue;
            }
            foreach ($entry->getLines() as $line) {
                $account = $line->getAccount();
                $party = $line->getParty();
                $auxiliary = null !== $party || $account->getGeneralNumber() !== $account->getNumber();
                $general = $auxiliary ? $account->getGeneralNumber() : $account->getNumber();
                $currency = $book->getCurrency();

                $rows[] = implode("\t", array_map(self::clean(...), [
                    $entry->getJournal()->getCode(),
                    $entry->getJournal()->getLabel(),
                    $entry->getNumber(),
                    $entry->getDate()->format('Ymd'),
                    $general,
                    $auxiliary ? ($labels[$general] ?? $account->getLabel()) : $account->getLabel(),
                    $auxiliary ? $account->getNumber() : '',
                    $auxiliary ? ($party?->getName() ?? $account->getLabel()) : '',
                    $entry->getPiece() ?? $entry->getNumber(),
                    $entry->getDate()->format('Ymd'),
                    $line->getLabel() ?? $entry->getLabel(),
                    self::amount($line->getDebit()),
                    self::amount($line->getCredit()),
                    $line->getLetter() ?? '',
                    $line->getLetteredOn()?->format('Ymd') ?? '',
                    $entry->getValidatedAt()->format('Ymd'),
                    'EUR' === $currency ? '' : self::amount($line->getDebit() - $line->getCredit()),
                    'EUR' === $currency ? '' : $currency,
                ]));
            }
        }

        return implode("\r\n", $rows)."\r\n";
    }

    /** 123456 -> "1234,56" */
    public static function amount(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign.intdiv($minor, 100).','.str_pad((string) ($minor % 100), 2, '0', \STR_PAD_LEFT);
    }

    /** No tab, pipe nor line break inside a field: they separate fields and rows. */
    public static function clean(?string $value): string
    {
        return trim(preg_replace('/\s*[\t\r\n|]+\s*/', ' ', (string) $value));
    }
}
