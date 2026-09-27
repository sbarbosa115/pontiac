<?php

declare(strict_types=1);

namespace App\Api\Output;

/** An arrow of a flow. */
final readonly class FlowTransitionOutput
{
    public function __construct(
        public string $from,
        public string $to,
        /** A FlowTrigger value: "manual", "lead_submitted", "session_booked", … */
        public string $trigger,
    ) {
    }
}
