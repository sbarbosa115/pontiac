<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Someone waiting in a stage longer than its alert (Inicio). */
final readonly class OverdueOutput
{
    public function __construct(
        public string $contactId,
        public string $fullName,
        public string $flowId,
        public string $flowName,
        public string $stageName,
        public int $days,
    ) {
    }
}
