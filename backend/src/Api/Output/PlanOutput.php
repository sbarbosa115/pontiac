<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Something the consultant sells (Planes). */
final readonly class PlanOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public MoneyOutput $price,
        /** Price zero: booked straight from a page. */
        public bool $free,
        public int $sessions,
        public int $durationMinutes,
        public bool $active,
    ) {
    }
}
