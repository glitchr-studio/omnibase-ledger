<?php

namespace Base\Ledger\Exception;

/** Lines that cannot be lettered together: they do not cancel out, or not on one account and party. */
class LetteringException extends LedgerException
{
}
