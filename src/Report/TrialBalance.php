<?php

namespace Base\Ledger\Report;

use Base\Ledger\Entity\Book;
use Base\Ledger\Enum\AccountType;
use Base\Ledger\Repository\EntryLineRepository;

/**
 * The balance générale: per account, the debit and credit totals over a
 * period and the balance they leave, with totals per class and overall
 * (debits = credits, or the books are wrong).
 */
class TrialBalance
{
    public function __construct(private readonly EntryLineRepository $lines)
    {
    }

    /**
     * @return array{
     *     rows: list<array{number: string, label: string, type: string, debit: int, credit: int, balance: int, debitBalance: int, creditBalance: int}>,
     *     classes: array<string, array{debit: int, credit: int, balance: int}>,
     *     totals: array{debit: int, credit: int, debitBalance: int, creditBalance: int},
     *     result: int,
     *     balanced: bool
     * }
     */
    public function compute(Book $book, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        return self::fromTotals($this->lines->totalsByAccount($book, $from, $to));
    }

    /** @param list<array{number: string, label: string, type: mixed, debit: int, credit: int}> $totals */
    public static function fromTotals(array $totals): array
    {
        $rows = [];
        $classes = [];
        $sum = ['debit' => 0, 'credit' => 0, 'debitBalance' => 0, 'creditBalance' => 0];
        $result = 0;
        foreach ($totals as $row) {
            $debit = (int) $row['debit'];
            $credit = (int) $row['credit'];
            $balance = $debit - $credit;
            $type = self::typeOf($row);
            $rows[] = [
                'number' => (string) $row['number'],
                'label' => (string) $row['label'],
                'type' => $type->value,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
                'debitBalance' => max($balance, 0),
                'creditBalance' => max(-$balance, 0),
            ];
            $class = substr((string) $row['number'], 0, 1);
            $classes[$class] ??= ['debit' => 0, 'credit' => 0, 'balance' => 0];
            $classes[$class]['debit'] += $debit;
            $classes[$class]['credit'] += $credit;
            $classes[$class]['balance'] += $balance;
            $sum['debit'] += $debit;
            $sum['credit'] += $credit;
            $sum['debitBalance'] += max($balance, 0);
            $sum['creditBalance'] += max(-$balance, 0);
            if ($type->isResult()) {
                $result -= $balance; // income is credit (negative balance): a profit is positive
            }
        }
        usort($rows, fn (array $a, array $b) => strcmp($a['number'], $b['number']));
        ksort($classes);

        return ['rows' => $rows, 'classes' => $classes, 'totals' => $sum, 'result' => $result, 'balanced' => $sum['debit'] === $sum['credit']];
    }

    /** A totals row's account type: the enum, its value, or the PCG's guess from the number. */
    public static function typeOf(array $row): AccountType
    {
        return $row['type'] instanceof AccountType ? $row['type'] : (AccountType::tryFrom((string) $row['type']) ?? AccountType::guess((string) $row['number']));
    }
}
