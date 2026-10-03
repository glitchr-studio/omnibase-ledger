<?php

namespace Base\Ledger\Enum;

/** Who a party is to the book: the collective account its own account sits under. */
enum PartyKind: string
{
    case TENANT = 'tenant';
    case SUPPLIER = 'supplier';
    case ASSOCIATE = 'associate';
    case OTHER = 'other';

    /** The PCG collective account a party of this kind gets an auxiliary account under. */
    public function collective(): string
    {
        return match ($this) {
            self::TENANT => '411',
            self::SUPPLIER => '401',
            self::ASSOCIATE => '455',
            self::OTHER => '467',
        };
    }
}
