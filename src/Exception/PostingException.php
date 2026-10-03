<?php

namespace Base\Ledger\Exception;

/** An entry Posting refuses: unbalanced, a negative or two-sided line, an unknown account or journal. */
class PostingException extends LedgerException
{
}
