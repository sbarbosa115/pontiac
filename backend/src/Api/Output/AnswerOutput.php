<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One extra field of a form, as it was labelled and answered. */
final readonly class AnswerOutput
{
    public function __construct(
        public string $key,
        public string $label,
        public string $value,
    ) {
    }
}
