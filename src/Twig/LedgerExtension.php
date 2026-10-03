<?php

namespace Base\Ledger\Twig;

use Base\Ledger\Entity\BankAccount;
use Twig\Attribute\AsTwigFilter;

/**
 * {{ 123456|ledger_money }} → 1 234,56 € (minor units, the locale's way);
 * {{ 123456|ledger_amount }} → 1 234,56 (no currency: a column of a report);
 * {{ iban|ledger_iban }} → FR76 •••• 4321.
 */
final class LedgerExtension
{
    #[AsTwigFilter('ledger_money')]
    public function money(int|string|null $minor, string $currency = 'EUR', ?string $locale = null): string
    {
        $value = ((int) $minor) / 100;
        if (class_exists(\NumberFormatter::class)) {
            $formatted = (new \NumberFormatter($locale ?? \Locale::getDefault(), \NumberFormatter::CURRENCY))->formatCurrency($value, $currency);
            if (false !== $formatted) {
                return $formatted;
            }
        }

        return number_format($value, 2, ',', ' ').' '.$currency;
    }

    #[AsTwigFilter('ledger_amount')]
    public function amount(int|string|null $minor): string
    {
        return null === $minor || 0 === (int) $minor ? '' : number_format(((int) $minor) / 100, 2, ',', ' ');
    }

    #[AsTwigFilter('ledger_iban')]
    public function iban(?string $iban): string
    {
        return BankAccount::mask($iban);
    }
}
