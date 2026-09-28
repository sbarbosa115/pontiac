<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One campaign parameter of the page's address when the form was sent (utm_source…). */
final readonly class UtmOutput
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }
}
