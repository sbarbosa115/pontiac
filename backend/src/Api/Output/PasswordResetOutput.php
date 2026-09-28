<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A new password is set. */
final readonly class PasswordResetOutput
{
    public function __construct(
        /** Where they sign in now: "/login" or "/<consultant>/portal/ingresar". */
        public string $loginPath,
    ) {
    }
}
