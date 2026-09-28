<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A template, its sections in their default order, and whether new pages may use it. */
final readonly class TemplateOutput
{
    public function __construct(
        /** A PageTemplate value. */
        public string $key,
        public bool $enabled,
        /** @var list<TemplateSectionOutput> */
        public array $sections,
    ) {
    }
}
