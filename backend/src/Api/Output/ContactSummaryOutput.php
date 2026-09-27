<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One contact in the list (Prospectos). */
final readonly class ContactSummaryOutput
{
    public function __construct(
        public string $id,
        public string $fullName,
        public string $email,
        public ?string $phone,
        /** "lead", "client" or "finished". */
        public string $status,
        public ?CategoryRefOutput $category,
        /** The page that brought them first. */
        public ?PageRefOutput $sourcePage,
        /** ISO 8601. */
        public string $lastActivityAt,
        /** Their data was erased on request (Ley 1581). */
        public bool $anonymized,
    ) {
    }
}
