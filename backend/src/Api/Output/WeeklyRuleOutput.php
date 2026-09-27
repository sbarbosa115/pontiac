<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Hours of one weekday, local time. */
final readonly class WeeklyRuleOutput
{
    public function __construct(
        /** ISO: 1 Monday … 7 Sunday. */
        public int $weekday,
        /** "09:00" */
        public string $from,
        /** "12:00" */
        public string $to,
    ) {
    }
}
