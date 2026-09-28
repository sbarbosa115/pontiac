<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A payment of the client's. */
final readonly class PortalPaymentOutput
{
    public function __construct(
        public string $reference,
        public MoneyOutput $amount,
        /** "pending", "approved", "declined", "voided" or "error". */
        public string $status,
        public ?string $method,
        /** Recorded by the consultant (cash, transfer): known by its day only. */
        public bool $manual,
        /** ISO 8601. */
        public string $createdAt,
        /** ISO 8601. */
        public ?string $paidAt,
    ) {
    }
}
