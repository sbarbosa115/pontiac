<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A contact, named where something else is the subject (a session). */
final readonly class ContactRefOutput
{
    public function __construct(
        public string $id,
        public string $fullName,
        public string $email,
    ) {
    }
}
