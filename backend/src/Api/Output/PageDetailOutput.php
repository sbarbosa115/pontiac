<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One page, as the editor opens it. */
final readonly class PageDetailOutput
{
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public string $path,
        public string $template,
        public string $status,
        public bool $home,
        public bool $hasUnpublishedChanges,
        /** ISO 8601. */
        public string $updatedAt,
        /** ISO 8601; null until first published. */
        public ?string $publishedAt,
        /** @var array<string, mixed> sections, form, seo, settings (see TemplateCatalog::newContent()) */
        public array $draft,
    ) {
    }
}
