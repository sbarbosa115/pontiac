<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Where to send the person to pay. */
final readonly class CheckoutOutput
{
    public function __construct(
        /** Wompi's checkout. */
        public string $checkoutUrl,
    ) {
    }
}
