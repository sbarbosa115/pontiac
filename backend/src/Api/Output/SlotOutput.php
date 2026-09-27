<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A free slot. */
final readonly class SlotOutput
{
    public function __construct(
        /** ISO 8601 (UTC): what to send back to book it. */
        public string $startsAt,
        /** "10:00 a. m.", local time. */
        public string $label,
    ) {
    }
}
