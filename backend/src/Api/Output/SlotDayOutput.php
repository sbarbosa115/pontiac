<?php

declare(strict_types=1);

namespace App\Api\Output;

/** The free slots of one day, local time. */
final readonly class SlotDayOutput
{
    public function __construct(
        /** "martes, 6 de octubre de 2026" */
        public string $label,
        /** @var list<SlotOutput> */
        public array $slots,
    ) {
    }
}
