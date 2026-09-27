<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One consultant in the super admin's list (Asesores). */
final readonly class AccountSummaryOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public bool $active,
        /** ISO 8601. */
        public string $createdAt,
        /** null only for an account created before owners were invited with it. */
        public ?AccountOwnerOutput $owner,
        public int $assistants,
        public int $clients,
    ) {
    }
}
