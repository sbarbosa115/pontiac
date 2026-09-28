<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Where a person is in one flow. */
final readonly class ContactFlowOutput
{
    public function __construct(
        public string $flowId,
        public string $flowName,
        public string $stageId,
        public string $stageName,
        /** ISO 8601. */
        public string $enteredAt,
        /** @var list<StageRefOutput> the stages they can be moved to */
        public array $stages,
    ) {
    }
}
