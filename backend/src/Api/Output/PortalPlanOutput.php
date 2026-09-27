<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A plan the client has, with its progress and payments. */
final readonly class PortalPlanOutput
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
        /** Waiting for payment, and the consultant takes online payments. */
        public bool $payable,
        /** ISO 8601. */
        public string $createdAt,
        /** @var list<PortalPaymentOutput> newest first */
        public array $payments,
    ) {
    }
}
