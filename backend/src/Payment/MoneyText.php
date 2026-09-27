<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\Account;

/** "$ 250.000" in the account's locale: whole amounts without cents, as prices are read in Colombia. */
final class MoneyText
{
    public static function format(Account $account, string $amount, string $currency): string
    {
        $formatter = new \NumberFormatter(str_replace('_', '-', $account->getLocale()), \NumberFormatter::CURRENCY);
        $whole = 1 === preg_match('/\.0+$/', $amount) || !str_contains($amount, '.');
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $whole ? 0 : 2);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $whole ? 0 : 2);

        // Formatting only: the amount itself stays a decimal string everywhere else.
        return (string) $formatter->formatCurrency((float) $amount, $currency);
    }
}
