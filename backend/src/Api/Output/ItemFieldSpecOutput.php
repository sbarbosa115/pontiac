<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A field of one item of an "items" field. */
final readonly class ItemFieldSpecOutput
{
    public function __construct(
        public string $name,
        public string $kind,
        public bool $required,
        public ?int $max,
    ) {
    }
}
