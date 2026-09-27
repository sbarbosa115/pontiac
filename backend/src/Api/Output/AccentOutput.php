<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A colour a page can take. */
final readonly class AccentOutput
{
    public function __construct(
        public string $key,
        /** Its main colour, for the picker's swatch. */
        public string $color,
    ) {
    }
}
