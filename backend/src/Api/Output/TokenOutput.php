<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A signed-in session: the JWT to send as "Authorization: Bearer …" (POST /api/login, POST /api/portal-login). */
final readonly class TokenOutput
{
    public function __construct(
        public string $token,
    ) {
    }
}
