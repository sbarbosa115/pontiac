<?php

declare(strict_types=1);

namespace App\Api\Output;

/** The account a user works in, and how the UI formats money and dates for it. */
final readonly class MeAccountOutput
{
    public function __construct(
        public string $id,
        public string $name,
        /** The first segment of its public URLs: /<slug>, /<slug>/portal. */
        public string $slug,
        public string $country,
        public string $currency,
        public string $locale,
        public string $timezone,
    ) {
    }
}
