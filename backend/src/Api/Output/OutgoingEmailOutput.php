<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One attempt to send an email (Correos). */
final readonly class OutgoingEmailOutput
{
    public function __construct(
        public string $id,
        /** What it is: "invitation", "test", … */
        public string $kind,
        public string $recipient,
        public string $subject,
        /** "sent" or "failed". */
        public string $status,
        /** Why it failed, as the mail server said it; null when sent. */
        public ?string $error,
        /** ISO 8601. */
        public string $sentAt,
        /** The consultant it was sent for; null for the platform's own emails. */
        public ?AccountRefOutput $account,
    ) {
    }
}
