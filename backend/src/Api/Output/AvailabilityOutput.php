<?php

declare(strict_types=1);

namespace App\Api\Output;

/** When the consultant takes sessions (Agenda › Disponibilidad). Times are local to $timezone. */
final readonly class AvailabilityOutput
{
    public function __construct(
        /** @var list<WeeklyRuleOutput> */
        public array $weeklyRules,
        /** @var list<AvailabilityExceptionOutput> */
        public array $exceptions,
        public int $bufferMinutes,
        public int $minNoticeHours,
        public int $bookingWindowDays,
        public int $clientCancelHours,
        /** @var list<int> hours before a session, largest first */
        public array $reminderHours,
        public string $meetingLink,
        public string $timezone,
    ) {
    }
}
