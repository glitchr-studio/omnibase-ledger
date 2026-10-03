<?php

namespace Base\Ledger\Model;

use Base\Ledger\Entity\Book;
use Base\Ledger\Entity\Party;

/**
 * An entry to post, as the application describes it - account numbers and
 * a journal code rather than entities:
 *
 *   $posting->post((new EntryDraft($book, 'VT', $date, 'Loyer octobre U3', 'notice:2026-10-U3', 'AVIS-2026-10-U3'))
 *       ->line('411U3', 85000, 0, $tenant)
 *       ->line('706', 0, 85000));
 *
 * $source identifies what the entry records, once: posting the same source
 * again returns the entry already there.
 */
final class EntryDraft
{
    /** @var list<array{account: string, debit: int, credit: int, party: ?Party, label: ?string}> */
    private array $lines = [];

    public function __construct(
        public readonly Book $book,
        public readonly string $journal,
        public readonly \DateTimeImmutable $date,
        public readonly string $label,
        public readonly string $source,
        public readonly ?string $piece = null,
    ) {
    }

    /** A line: amounts in minor units, one of them zero. */
    public function line(string $account, int $debit, int $credit, ?Party $party = null, ?string $label = null): self
    {
        $this->lines[] = ['account' => $account, 'debit' => $debit, 'credit' => $credit, 'party' => $party, 'label' => $label];

        return $this;
    }

    /** @return list<array{account: string, debit: int, credit: int, party: ?Party, label: ?string}> */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getTotalDebit(): int
    {
        return array_sum(array_column($this->lines, 'debit'));
    }

    public function getTotalCredit(): int
    {
        return array_sum(array_column($this->lines, 'credit'));
    }
}
