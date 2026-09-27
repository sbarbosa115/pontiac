<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A session, as the client sees it. */
final readonly class PortalSessionOutput
{
    public function __construct(
        public string $id,
        /** ISO 8601 (UTC). */
        public string $startsAt,
        /** ISO 8601 (UTC). */
        public string $endsAt,
        /** "scheduled", "done", "no_show" or "cancelled". */
        public string $status,
        public string $planName,
        public int $durationMinutes,
        public string $meetingLink,
        public ?string $cancelReason,
        /** Before the consultant's cancellation limit: they may move or cancel it. */
        public bool $canChange,
    ) {
    }
}
