<?php

declare(strict_types=1);

namespace App\Api\Output;

/** An amount: a decimal string and its currency, never a float. */
final readonly class MoneyOutput
{
    public function __construct(
        /** "250000.00" */
        public string $amount,
        /** ISO 4217, e.g. "COP". */
        public string $currency,
    ) {
    }
}
