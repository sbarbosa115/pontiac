<?php

declare(strict_types=1);

namespace App\Api\Output;

/** What the page editor needs to know about templates: GET /api/admin/pages/catalog. */
final readonly class PageCatalogOutput
{
    public function __construct(
        /** @var list<TemplateOutput> */
        public array $templates,
        /** @var list<SectionTypeOutput> */
        public array $sectionTypes,
        /** @var list<AccentOutput> */
        public array $accents,
        /** @var list<string> the types an extra form field can have */
        public array $fieldTypes,
        public int $maxExtraFields,
    ) {
    }
}
