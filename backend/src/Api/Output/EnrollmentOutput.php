<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A plan a contact has (Planes y pagos): its progress and its payments. */
final readonly class EnrollmentOutput
{
    public function __construct(
        public string $id,
        public string $planName,
        public MoneyOutput $price,
        public bool $free,
        public int $sessionsIncluded,
        /** Booked, done or no-show. */
        public int $sessionsTaken,
        /** Done or no-show. */
        public int $sessionsUsed,
        public int $durationMinutes,
        /** "pending_payment", "active", "completed" or "cancelled". */
        public string $status,
        /** Once completed: "renewed" or "finished". */
        public ?string $outcome,
        public ?PageRefOutput $sourcePage,
        /** ISO 8601. */
        public string $createdAt,
        /** ISO 8601. */
        public ?string $completedAt,
        /** The link to pay it, while it waits for a payment. */
        public ?string $paymentUrl,
        /** @var list<PaymentOutput> newest first */
        public array $payments,
    ) {
    }
}
