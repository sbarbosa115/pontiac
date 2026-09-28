<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A consultant, named where something else is the subject (an email, a login). */
final readonly class AccountRefOutput
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
