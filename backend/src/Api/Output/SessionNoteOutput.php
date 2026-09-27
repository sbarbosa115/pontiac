<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A note of a session. */
final readonly class SessionNoteOutput
{
    public function __construct(
        public string $id,
        public string $body,
        /** "private" (owner only) or "shared". */
        public string $visibility,
        public PersonOutput $author,
        /** ISO 8601. */
        public string $createdAt,
        /** ISO 8601. */
        public string $updatedAt,
        /** Whether the person asking may change it (its author, or the owner). */
        public bool $editable,
    ) {
    }
}
