<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A flow with its stages, arrows, and the pages that feed it (the editor). */
final readonly class FlowOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        /** @var list<FlowStageOutput> in board order */
        public array $stages,
        /** @var list<FlowTransitionOutput> */
        public array $transitions,
        /** @var list<PageRefOutput> pages whose people enter this flow */
        public array $pages,
    ) {
    }
}
