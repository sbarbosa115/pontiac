<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A field of a section type (TemplateCatalog::SECTION_TYPES): what the editor draws for it, and its limits. */
final readonly class FieldSpecOutput
{
    public function __construct(
        public string $name,
        /** "text", "textarea", "image", "date", "url" or "items". */
        public string $kind,
        public bool $required,
        /** Characters, for text kinds. */
        public ?int $max,
        /** For "items": how many at most. */
        public ?int $maxItems,
        /** @var list<ItemFieldSpecOutput> for "items": the fields of each item */
        public array $fields,
    ) {
    }
}
