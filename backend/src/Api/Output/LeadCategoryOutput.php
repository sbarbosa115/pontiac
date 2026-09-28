<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One of the consultant's lead categories (Ajustes › Categorías). */
final readonly class LeadCategoryOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $color,
        public bool $active,
    ) {
    }
}
