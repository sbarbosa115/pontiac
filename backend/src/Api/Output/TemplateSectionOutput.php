<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A section a template has. */
final readonly class TemplateSectionOutput
{
    public function __construct(
        public string $id,
        /** A key of PageCatalogOutput::$sectionTypes. */
        public string $type,
    ) {
    }
}
