<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One line of a person's Historial: a move in a flow, or an email sent to them. */
final readonly class HistoryItemOutput
{
    public function __construct(
        /** "flow" or "email". */
        public string $type,
        /** ISO 8601. */
        public string $at,
        public ?string $flowName,
        public ?string $fromStage,
        public ?string $toStage,
        /** For a move: a FlowTrigger value, "added", "removed" or "manual". For an email: its kind. */
        public string $reason,
        /** Who moved them, when a person did. */
        public ?string $by,
        /** For an email: its subject and whether it left. */
        public ?string $subject,
        public ?string $emailStatus,
    ) {
    }
}
