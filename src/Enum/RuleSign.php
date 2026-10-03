<?php

namespace Base\Ledger\Enum;

/** Which bank lines a reconciliation rule looks at: money coming in, going out, or both. */
enum RuleSign: string
{
    case ANY = 'any';
    case IN = 'in';
    case OUT = 'out';

    public function accepts(int $amount): bool
    {
        return match ($this) {
            self::ANY => 0 !== $amount,
            self::IN => $amount > 0,
            self::OUT => $amount < 0,
        };
    }
}
