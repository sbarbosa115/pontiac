<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A note the consultant shared. */
final readonly class PortalNoteOutput
{
    public function __construct(
        public string $id,
        public string $body,
        public string $author,
        /** ISO 8601. */
        public string $createdAt,
        public string $sessionId,
        /** ISO 8601 (UTC). */
        public string $sessionStartsAt,
        public string $planName,
    ) {
    }
}
