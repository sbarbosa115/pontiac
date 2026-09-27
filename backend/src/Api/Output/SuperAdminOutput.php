<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One of Pontiac's administrators (Configuración › Administradores). */
final readonly class SuperAdminOutput
{
    public function __construct(
        public string $id,
        public string $email,
        public string $fullName,
        public bool $active,
        /** "active", "invited" or "none". */
        public string $loginStatus,
        /** ISO 8601; null until they first sign in. */
        public ?string $lastSignInAt,
        /** The super admin reading the list: they cannot disable themselves. */
        public bool $you,
    ) {
    }
}
