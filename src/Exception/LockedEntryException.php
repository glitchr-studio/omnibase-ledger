<?php

namespace Base\Ledger\Exception;

/** A change to a validated entry or its lines (other than their lettering): reverse it instead. */
class LockedEntryException extends LedgerException
{
}
