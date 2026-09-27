<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A flow in the list (Flujos). */
final readonly class FlowSummaryOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        public int $stages,
        /** People in it now. */
        public int $people,
    ) {
    }
}
