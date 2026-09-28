<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One form a contact sent. */
final readonly class SubmissionOutput
{
    public function __construct(
        public string $id,
        /** ISO 8601. */
        public string $submittedAt,
        public PageRefOutput $page,
        /** @var list<AnswerOutput> */
        public array $answers,
        /** @var list<UtmOutput> */
        public array $utm,
        public ?string $referrer,
    ) {
    }
}
