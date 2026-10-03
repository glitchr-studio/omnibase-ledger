<?php

namespace Base\Ledger\Report;

use Base\Ledger\Entity\Book;
use Base\Ledger\Repository\EntryLineRepository;
use Base\Ledger\Repository\FiscalYearRepository;

/**
 * The bilan at a date: every account's balance from the book's first entry
 * to that day, the assets (actif) on one side, what finances them (passif)
 * on the other - and, when asked, the same day a year earlier beside it.
 *
 * - Class 1 is always passif: capital and reserves (10-14), loans (16),
 *   tenants' deposits (165); what it holds on the wrong side shows negative.
 * - Class 2 is always actif, net: depreciation (28) shows negative under
 *   the fixed asset group.
 * - The other balance sheet accounts go by their balance: a tenant who owes
 *   (411 debit) is a receivable, one who overpaid (411 credit) a debt; the
 *   bank (51, 53) is cash, overdrawn a debt; suppliers (40) and associates'
 *   current accounts (455) are debts, or receivables when debit.
 * - The income and expense accounts (by type) are not closed in the books:
 *   their balance is the result, in the capitaux propres - the current
 *   fiscal year's, and before it whatever earlier years left unallocated
 *   (no closing entry posted to 120/129 and the report à nouveau).
 *
 * Actif and passif then add up to the same total, or the books are wrong.
 * Draft entries count, as in the trial balance.
 */
class BalanceSheet
{
    /** key => heading, in the order of the bilan */
    public const ASSETS = [
        'fixed_assets' => 'Immobilisations nettes',
        'receivables' => 'Créances locataires et clients',
        'other_assets' => 'Autres créances',
        'cash' => 'Disponibilités',
    ];

    /** key => heading, in the order of the bilan */
    public const LIABILITIES = [
        'equity' => 'Capitaux propres',
        'loans' => 'Emprunts',
        'deposits' => 'Dépôts de garantie reçus',
        'suppliers' => 'Dettes fournisseurs',
        'associates' => 'Comptes courants d\'associés',
        'other_liabilities' => 'Autres dettes',
    ];

    public const RESULT = 'Résultat de l\'exercice';
    public const EARLIER_RESULTS = 'Résultats antérieurs non affectés';

    public function __construct(
        private readonly EntryLineRepository $lines,
        private readonly FiscalYearRepository $fiscalYears,
    ) {
    }

    /**
     * Amounts are in minor units, positive on their normal side. The
     * result lines have no account number. previous is null throughout
     * unless $withPrevious.
     *
     * @return array{
     *     assets: array<string, array{key: string, label: string, lines: list<array{number: ?string, label: string, amount: int, previous: ?int}>, total: int, previous: ?int}>,
     *     liabilities: array<string, array{key: string, label: string, lines: list<array{number: ?string, label: string, amount: int, previous: ?int}>, total: int, previous: ?int}>,
     *     totals: array{assets: int, liabilities: int, result: int},
     *     previous: ?array{assets: int, liabilities: int, result: int},
     *     balanced: bool
     * }
     */
    public function compute(Book $book, \DateTimeImmutable $at, bool $withPrevious = false): array
    {
        [$totals, $year] = $this->column($book, $at);
        [$previous, $previousYear] = $withPrevious ? $this->column($book, IncomeStatement::yearBefore($at)) : [null, null];

        return self::fromTotals($totals, $year, $previous, $previousYear);
    }

    /**
     * @param list<array{number: string, label: string, type: mixed, debit: int, credit: int}>      $totals       every entry up to the date
     * @param list<array{number: string, label: string, type: mixed, debit: int, credit: int}>|null $yearTotals   the fiscal year's entries up to it: the result they make is the year's, the rest of the result earlier years'; null, all of it is the year's
     * @param list<array{number: string, label: string, type: mixed, debit: int, credit: int}>|null $previous     the same a year earlier (N-1)
     * @param list<array{number: string, label: string, type: mixed, debit: int, credit: int}>|null $previousYear
     */
    public static function fromTotals(array $totals, ?array $yearTotals = null, ?array $previous = null, ?array $previousYear = null): array
    {
        $compare = null !== $previous;
        $report = ['assets' => self::groups(self::ASSETS, $compare), 'liabilities' => self::groups(self::LIABILITIES, $compare)];
        $results = ['amount' => 0, 'previous' => 0];
        foreach (['amount' => [$totals, $yearTotals], 'previous' => [$previous ?? [], $previousYear]] as $column => [$rows, $yearRows]) {
            $result = 0;
            foreach ($rows as $row) {
                $balance = (int) $row['debit'] - (int) $row['credit'];
                if (TrialBalance::typeOf($row)->isResult()) {
                    $result -= $balance; // income is credit: a profit is positive
                    continue;
                }
                if (0 === $balance) {
                    continue;
                }
                $number = (string) $row['number'];
                [$side, $key] = self::place($number, $balance);
                self::add($report[$side][$key], $column, $number, (string) $row['label'], 'assets' === $side ? $balance : -$balance, $compare);
            }
            $yearResult = null === $yearRows ? $result : TrialBalance::fromTotals($yearRows)['result'];
            if ($result !== $yearResult) {
                self::add($report['liabilities']['equity'], $column, 'earlier', self::EARLIER_RESULTS, $result - $yearResult, $compare);
            }
            if ('amount' === $column || $compare) {
                self::add($report['liabilities']['equity'], $column, 'result', self::RESULT, $yearResult, $compare);
            }
            $results[$column] = $yearResult;
        }

        foreach ($report as $side => $groups) {
            foreach ($groups as $key => $group) {
                // Accounts by number, then the earlier results and the year's, last of the capitaux propres.
                uksort($group['lines'], fn (string $a, string $b) => [!ctype_digit($a[0]), 'result' === $a] <=> [!ctype_digit($b[0]), 'result' === $b] ?: strcmp($a, $b));
                $report[$side][$key]['lines'] = array_values($group['lines']);
            }
        }
        $sum = fn (string $side, string $field) => array_sum(array_column($report[$side], $field));
        $report['totals'] = ['assets' => $sum('assets', 'total'), 'liabilities' => $sum('liabilities', 'total'), 'result' => $results['amount']];
        $report['previous'] = $compare ? ['assets' => $sum('assets', 'previous'), 'liabilities' => $sum('liabilities', 'previous'), 'result' => $results['previous']] : null;
        $report['balanced'] = $report['totals']['assets'] === $report['totals']['liabilities']
            && (!$compare || $report['previous']['assets'] === $report['previous']['liabilities']);

        return $report;
    }

    /** @return array{0: 'assets'|'liabilities', 1: string} where a balance sheet account's balance (debit minus credit) goes */
    public static function place(string $number, int $balance): array
    {
        $under = fn (string ...$prefixes) => [] !== array_filter($prefixes, fn (string $prefix) => str_starts_with($number, $prefix));

        return match (true) {
            $under('165') => ['liabilities', 'deposits'],
            $under('16') => ['liabilities', 'loans'],
            $under('10', '11', '12', '13', '14') => ['liabilities', 'equity'],
            $under('1') => ['liabilities', 'other_liabilities'],
            $under('2') => ['assets', 'fixed_assets'],
            $under('3') => ['assets', 'other_assets'],
            $balance >= 0 && $under('41') => ['assets', 'receivables'],
            $balance >= 0 && $under('51', '53') => ['assets', 'cash'],
            $balance >= 0 => ['assets', 'other_assets'],
            $under('40') => ['liabilities', 'suppliers'],
            $under('455') => ['liabilities', 'associates'],
            default => ['liabilities', 'other_liabilities'],
        };
    }

    /** One column's amount on a group's line (an account, or "earlier"/"result"), and on its total. */
    private static function add(array &$group, string $column, string $key, string $label, int $amount, bool $compare): void
    {
        $group['lines'][$key] ??= ['number' => ctype_digit($key[0]) ? $key : null, 'label' => $label, 'amount' => 0, 'previous' => $compare ? 0 : null];
        $group['lines'][$key][$column] += $amount;
        $group['amount' === $column ? 'total' : 'previous'] += $amount;
    }

    /** @return array{0: list<array>, 1: list<array>} the totals up to $at, and those of its fiscal year */
    private function column(Book $book, \DateTimeImmutable $at): array
    {
        $start = $this->fiscalYears->current($book, $at)->getStart();

        return [$this->lines->totalsByAccount($book, null, $at), $this->lines->totalsByAccount($book, $start, $at)];
    }

    private static function groups(array $definitions, bool $compare): array
    {
        $groups = [];
        foreach ($definitions as $key => $label) {
            $groups[$key] = ['key' => $key, 'label' => $label, 'lines' => [], 'total' => 0, 'previous' => $compare ? 0 : null];
        }

        return $groups;
    }
}
