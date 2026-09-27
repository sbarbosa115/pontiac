<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A lead category, named where a contact shows it. */
final readonly class CategoryRefOutput
{
    public function __construct(
        public string $id,
        public string $name,
        /** One of the UI tones (LeadCategory::COLORS). */
        public string $color,
        public bool $active,
    ) {
    }
}
