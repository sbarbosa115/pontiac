<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A page, named where something else is the subject (a contact, a submission). */
final readonly class PageRefOutput
{
    public function __construct(
        public string $id,
        public string $title,
        /** Its address inside the consultant's: /<consultant>/<slug>. */
        public string $slug,
    ) {
    }
}
