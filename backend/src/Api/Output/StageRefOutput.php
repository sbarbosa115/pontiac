<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A stage, by name. */
final readonly class StageRefOutput
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
