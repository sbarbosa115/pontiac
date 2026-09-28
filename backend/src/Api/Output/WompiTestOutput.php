<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Wompi answered for the public key. */
final readonly class WompiTestOutput
{
    public function __construct(
        /** The merchant's name at Wompi. */
        public string $merchantName,
        public string $mode,
    ) {
    }
}
