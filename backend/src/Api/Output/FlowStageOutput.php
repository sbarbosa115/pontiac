<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A stage of a flow, on the canvas. */
final readonly class FlowStageOutput
{
    public function __construct(
        public string $id,
        public string $name,
        /** "start", "step" or "end". */
        public string $kind,
        public int $x,
        public int $y,
        public ?string $emailTemplateId,
        public ?int $alertDays,
        /** People in it now. */
        public int $people,
    ) {
    }
}
