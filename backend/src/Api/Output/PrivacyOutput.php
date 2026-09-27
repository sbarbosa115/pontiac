<?php

declare(strict_types=1);

namespace App\Api\Output;

/** The consultant's privacy policy (Ajustes › Privacidad). */
final readonly class PrivacyOutput
{
    public function __construct(
        /** What the forms link to: the consultant's text, or the platform's default when they have none. */
        public string $text,
        public bool $usingDefault,
        public string $defaultText,
    ) {
    }
}
