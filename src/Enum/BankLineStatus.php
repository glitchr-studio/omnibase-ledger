<?php

namespace Base\Ledger\Enum;

/** Where a bank line stands in the reconciliation. */
enum BankLineStatus: string
{
    /** Nothing found for it yet. */
    case UNMATCHED = 'unmatched';
    /** Suggestions exist, none sure enough to apply on its own: someone decides. */
    case SUGGESTED = 'suggested';
    /** Posted in the bank journal (its entry) and lettered with what it pays. */
    case RECONCILED = 'reconciled';
    /** Deliberately left out of the accounts (an internal transfer already posted, a test). */
    case IGNORED = 'ignored';

    public function isPending(): bool
    {
        return self::UNMATCHED === $this || self::SUGGESTED === $this;
    }
}
