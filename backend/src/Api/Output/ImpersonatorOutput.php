<?php

declare(strict_types=1);

namespace App\Api\Output;

/** The super admin behind a request made as someone else (X-Switch-User). */
final readonly class ImpersonatorOutput
{
    public function __construct(
        public string $id,
        public string $email,
        public string $fullName,
    ) {
    }
}
