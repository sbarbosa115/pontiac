<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Who an invitation link is for, to greet them before they choose a password, and where they sign in after. */
final readonly class InvitationOutput
{
    public function __construct(
        public string $email,
        public string $fullName,
        /** "super_admin", "owner", "assistant" or "client". */
        public string $role,
        public ?string $accountName,
        /** A client signs in at /<slug>/portal; null for a super admin. */
        public ?string $accountSlug,
    ) {
    }
}
