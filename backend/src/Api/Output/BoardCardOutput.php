<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A person on the board. */
final readonly class BoardCardOutput
{
    public function __construct(
        public string $contactId,
        public string $fullName,
        /** "lead", "client" or "finished". */
        public string $status,
        public ?CategoryRefOutput $category,
        /** ISO 8601: when they entered this stage. */
        public string $enteredAt,
        /** Whole days in this stage. */
        public int $days,
        /** Longer than the stage's alert. */
        public bool $overdue,
    ) {
    }
}
