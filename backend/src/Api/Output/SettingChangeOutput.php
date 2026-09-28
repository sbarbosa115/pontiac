<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One setting in one save: its API name, and its value before and after, as text (a list is comma-separated). */
final readonly class SettingChangeOutput
{
    public function __construct(
        public string $field,
        public string $from,
        public string $to,
    ) {
    }
}
