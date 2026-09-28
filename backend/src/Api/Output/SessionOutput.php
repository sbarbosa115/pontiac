<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One session (Agenda). */
final readonly class SessionOutput
{
    public function __construct(
        public string $id,
        /** ISO 8601 (UTC). */
        public string $startsAt,
        /** ISO 8601 (UTC). */
        public string $endsAt,
        /** "scheduled", "done", "no_show" or "cancelled". */
        public string $status,
        public ContactRefOutput $contact,
        /** The plan's name as it was when they enrolled. */
        public string $planName,
        public int $durationMinutes,
        public string $meetingLink,
        public ?string $cancelReason,
        /** "visitor" (from a page) or "staff". */
        public string $bookedBy,
    ) {
    }
}
