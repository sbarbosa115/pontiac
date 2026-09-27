<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Who did something. */
final readonly class PersonOutput
{
    public function __construct(
        public string $id,
        public string $fullName,
        public string $email,
    ) {
    }
}
