<?php

namespace Base\Ledger\Exception;

/** A bank access that failed: Omnibank missing, a gateway or an account not found, the provider refusing. */
class BankException extends LedgerException
{
}
