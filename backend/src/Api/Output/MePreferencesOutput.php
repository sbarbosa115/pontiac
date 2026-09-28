<?php

declare(strict_types=1);

namespace App\Api\Output;

/** PATCH /api/me/preferences: the person's preferences, as saved. */
final readonly class MePreferencesOutput
{
    public function __construct(
        /** "light", "dark" or "system" (follow the device). */
        public string $uiTheme,
    ) {
    }
}
