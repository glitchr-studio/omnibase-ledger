<?php

namespace Base\Ledger\Enum;

/**
 * A bank connection's consent as the ledger keeps it: Omnibank's
 * ConsentStatus, stored by value so the entity does not depend on the
 * package being installed.
 */
enum ConsentState: string
{
    case ACTIVE = 'active';
    case NEEDS_RENEWAL = 'needs_renewal';
    case REVOKED = 'revoked';
    case NONE = 'none';

    /** From Omnibank's ConsentStatus (a backed or pure enum), by its value or its name. */
    public static function of(\UnitEnum|string|null $status): self
    {
        if (null === $status) {
            return self::NONE;
        }
        $value = \is_string($status) ? $status : ($status instanceof \BackedEnum ? (string) $status->value : $status->name);

        return self::tryFrom(strtolower($value)) ?? self::NONE;
    }
}
