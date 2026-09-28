<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Whether a contact can sign in to the portal. */
final readonly class PortalAccessOutput
{
    public function __construct(
        /** "none", "invited", "active" or "disabled". */
        public string $status,
        /** ISO 8601. */
        public ?string $lastSignInAt,
    ) {
    }
}
