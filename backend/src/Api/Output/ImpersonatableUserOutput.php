<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Someone a super admin may act as: a consultant (owner) or an assistant of an active account. */
final readonly class ImpersonatableUserOutput
{
    public function __construct(
        public string $id,
        public string $email,
        public string $fullName,
        /** "owner" or "assistant". */
        public string $role,
        public ImpersonatableAccountOutput $account,
    ) {
    }
}
