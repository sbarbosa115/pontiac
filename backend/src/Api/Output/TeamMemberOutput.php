<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Someone on the consultant's team: the owner or an assistant. */
final readonly class TeamMemberOutput
{
    public function __construct(
        public string $id,
        public string $email,
        public string $fullName,
        /** "owner" or "assistant". */
        public string $role,
        public bool $active,
        /** "active" (has a password), "invited" (link sent, not used yet) or "none". */
        public string $loginStatus,
        /** ISO 8601; null until they first sign in. */
        public ?string $lastSignInAt,
    ) {
    }
}
