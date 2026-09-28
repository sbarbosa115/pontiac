<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Prospectos › Tablero for one flow. */
final readonly class BoardOutput
{
    public function __construct(
        public string $flowId,
        public string $flowName,
        /** @var list<BoardColumnOutput> */
        public array $columns,
    ) {
    }
}
