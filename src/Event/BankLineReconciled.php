<?php

namespace Base\Ledger\Event;

use Base\Ledger\Entity\BankLine;
use Base\Ledger\Entity\Entry;
use Base\Ledger\Entity\EntryLine;

/**
 * A bank line posted and lettered with what it settles (Reconciler::apply):
 * what the application owes or is owed can follow - a rent call paid, an
 * invoice settled - from the open items it cleared, without posting again.
 */
final class BankLineReconciled
{
    /** @param list<EntryLine> $openItems */
    public function __construct(
        public readonly BankLine $line,
        public readonly Entry $entry,
        public readonly array $openItems,
    ) {
    }
}
