<?php

namespace Base\Ledger\Report;

use Base\Ledger\Entity\Book;
use Base\Ledger\Entity\EntryLine;
use Base\Ledger\Repository\EntryLineRepository;

/** The grand livre: every line of every account over a period, with the running balance and the account's totals. */
class GeneralLedger
{
    public function __construct(private readonly EntryLineRepository $lines)
    {
    }

    /**
     * @return list<array{number: string, label: string, lines: list<array{date: \DateTimeImmutable, journal: string, number: ?string, piece: ?string, label: string, party: ?string, debit: int, credit: int, balance: int, letter: ?string}>, debit: int, credit: int, balance: int}>
     */
    public function compute(Book $book, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?string $accountPrefix = null): array
    {
        return self::fromLines($this->lines->forLedger($book, $from, $to, $accountPrefix));
    }

    /** @param iterable<EntryLine> $lines by account then date */
    public static function fromLines(iterable $lines): array
    {
        $accounts = [];
        foreach ($lines as $line) {
            $account = $line->getAccount();
            $key = $account->getNumber();
            $accounts[$key] ??= ['number' => $key, 'label' => $account->getLabel(), 'lines' => [], 'debit' => 0, 'credit' => 0, 'balance' => 0];
            $accounts[$key]['debit'] += $line->getDebit();
            $accounts[$key]['credit'] += $line->getCredit();
            $accounts[$key]['balance'] += $line->getNet();
            $entry = $line->getEntry();
            $accounts[$key]['lines'][] = [
                'date' => $entry->getDate(),
                'journal' => $entry->getJournal()->getCode(),
                'number' => $entry->getNumber(),
                'piece' => $entry->getPiece(),
                'label' => $line->getLabel() ?? $entry->getLabel(),
                'party' => $line->getParty()?->getName(),
                'debit' => $line->getDebit(),
                'credit' => $line->getCredit(),
                'balance' => $accounts[$key]['balance'],
                'letter' => $line->getLetter(),
            ];
        }
        ksort($accounts, \SORT_STRING);

        return array_values($accounts);
    }
}
