<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Inicio of the portal. */
final readonly class PortalOverviewOutput
{
    public function __construct(
        /** The next scheduled session. */
        public ?PortalSessionOutput $nextSession,
        /** @var list<PortalPlanOutput> active and waiting for payment, newest first */
        public array $plans,
        /** Hours before a session until which they may move or cancel it. */
        public int $cancelHours,
    ) {
    }
}
