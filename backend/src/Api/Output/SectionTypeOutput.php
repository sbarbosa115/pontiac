<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A kind of section and the fields it has. */
final readonly class SectionTypeOutput
{
    public function __construct(
        public string $type,
        /** @var list<FieldSpecOutput> */
        public array $fields,
    ) {
    }
}
