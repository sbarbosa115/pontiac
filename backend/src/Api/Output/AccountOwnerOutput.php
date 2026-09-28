<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A consultant's owner, as the super admin sees them next to the consultant. */
final readonly class AccountOwnerOutput
{
    public function __construct(
        public string $id,
        public string $fullName,
        public string $email,
        /** "active" (has a password), "invited" or "none". */
        public string $loginStatus,
        /** ISO 8601; null until they first sign in. */
        public ?string $lastSignInAt,
    ) {
    }
}
