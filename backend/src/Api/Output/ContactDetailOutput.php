<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One contact, with every form they sent, their sessions and their plans. */
final readonly class ContactDetailOutput
{
    public function __construct(
        public string $id,
        public string $fullName,
        public string $email,
        public ?string $phone,
        public string $status,
        public ?CategoryRefOutput $category,
        public ?PageRefOutput $sourcePage,
        /** ISO 8601. */
        public string $createdAt,
        /** ISO 8601. */
        public string $lastActivityAt,
        /** ISO 8601: when they last accepted the privacy policy. */
        public string $consentAt,
        public bool $anonymized,
        /** @var list<SubmissionOutput> newest first */
        public array $submissions,
        /** @var list<SessionOutput> the latest first */
        public array $sessions,
        /** @var list<EnrollmentOutput> newest first */
        public array $enrollments,
    ) {
    }
}
