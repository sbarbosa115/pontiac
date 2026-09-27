<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A date whose hours differ from the week's: none (a day off), or these. */
final readonly class AvailabilityExceptionOutput
{
    public function __construct(
        /** Y-m-d */
        public string $date,
        public ?string $from,
        public ?string $to,
    ) {
    }
}
