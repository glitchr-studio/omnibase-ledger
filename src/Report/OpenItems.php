<?php

namespace Base\Ledger\Report;

use Base\Ledger\Entity\Book;
use Base\Ledger\Entity\EntryLine;
use Base\Ledger\Entity\Party;
use Base\Ledger\Service\Lettering;

/**
 * What is still owed, party by party: the unlettered lines of the accounts
 * under a prefix (411 - tenants - by default; 401 for suppliers), their
 * balance and how old the oldest is.
 */
class OpenItems
{
    public function __construct(private readonly Lettering $lettering)
    {
    }

    /**
     * @return array{
     *     parties: list<array{party: ?Party, name: string, account: string, items: list<EntryLine>, balance: int, oldest: ?\DateTimeImmutable}>,
     *     total: int
     * }
     */
    public function compute(Book $book, ?Party $party = null, ?string $accountPrefix = '411', ?\DateTimeImmutable $at = null): array
    {
        return self::fromLines($this->lettering->openItems($book, $party, $accountPrefix), $at);
    }

    /** @param iterable<EntryLine> $lines */
    public static function fromLines(iterable $lines, ?\DateTimeImmutable $at = null): array
    {
        $parties = [];
        $total = 0;
        foreach ($lines as $line) {
            if (null !== $at && $line->getEntry()->getDate() > $at) {
                continue;
            }
            $party = $line->getParty();
            $key = $line->getAccount()->getNumber().'|'.(null === $party ? '' : spl_object_id($party));
            $parties[$key] ??= ['party' => $party, 'name' => $party?->getName() ?? $line->getAccount()->getLabel(), 'account' => $line->getAccount()->getNumber(), 'items' => [], 'balance' => 0, 'oldest' => null];
            $parties[$key]['items'][] = $line;
            $parties[$key]['balance'] += $line->getNet();
            $date = $line->getEntry()->getDate();
            if (null === $parties[$key]['oldest'] || $date < $parties[$key]['oldest']) {
                $parties[$key]['oldest'] = $date;
            }
            $total += $line->getNet();
        }
        $parties = array_values(array_filter($parties, fn (array $p) => 0 !== $p['balance'] || \count($p['items']) > 0));
        usort($parties, fn (array $a, array $b) => abs($b['balance']) <=> abs($a['balance']) ?: strcmp($a['name'], $b['name']));

        return ['parties' => $parties, 'total' => $total];
    }
}
