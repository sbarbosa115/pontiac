<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One page in the list (Páginas). */
final readonly class PageSummaryOutput
{
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        /** Where visitors find it: /<consultant> for the home page, /<consultant>/<slug> for the rest. */
        public string $path,
        /** A PageTemplate value. */
        public string $template,
        /** "draft", "published" or "disabled". */
        public string $status,
        public bool $home,
        /** The draft differs from what visitors see. */
        public bool $hasUnpublishedChanges,
        public int $leadsLast30Days,
        /** ISO 8601. */
        public string $updatedAt,
        /** ISO 8601; null until first published. */
        public ?string $publishedAt,
    ) {
    }
}
