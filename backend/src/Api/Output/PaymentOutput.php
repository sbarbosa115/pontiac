<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One payment (Pagos, a contact's plans). */
final readonly class PaymentOutput
{
    public function __construct(
        public string $id,
        /** Ours, as Wompi shows it: "PON-…". */
        public string $reference,
        public MoneyOutput $amount,
        /** "pending", "approved", "declined", "voided" or "error". */
        public string $status,
        /** Wompi's (CARD, PSE, NEQUI, …) or a manual one (cash, transfer, other). */
        public ?string $method,
        public bool $manual,
        public ?string $note,
        /** Who recorded a manual payment. */
        public ?string $recordedBy,
        public ContactRefOutput $contact,
        public string $planName,
        public string $enrollmentId,
        /** ISO 8601. */
        public string $createdAt,
        /** ISO 8601. */
        public ?string $paidAt,
    ) {
    }
}
