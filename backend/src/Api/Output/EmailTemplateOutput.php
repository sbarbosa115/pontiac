<?php

declare(strict_types=1);

namespace App\Api\Output;

/** An email template (Ajustes › Correos). */
final readonly class EmailTemplateOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $subject,
        public string $body,
        public bool $active,
    ) {
    }
}
