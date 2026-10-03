<?php

namespace Base\Ledger\Exception;

/** A date on or before the book's lockedUntil, or in a closed fiscal year. */
class LockedPeriodException extends PostingException
{
}
