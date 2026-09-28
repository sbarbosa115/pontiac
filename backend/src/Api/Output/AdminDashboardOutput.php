<?php

declare(strict_types=1);

namespace App\Api\Output;

/** What a consultant sees first (Inicio). */
final readonly class AdminDashboardOutput
{
    public function __construct(
        public int $newLeadsLast7Days,
        public int $publishedPages,
        public int $maxPublishedPages,
        /** Scheduled sessions today and tomorrow, in the consultant's timezone. */
        public int $sessionsToday,
        public int $sessionsTomorrow,
        /** Approved payments in the last 7 days: how many and how much. */
        public int $paymentsLast7Days,
        public MoneyOutput $paidLast7Days,
        /** @var list<OverdueOutput> people waiting in a flow stage longer than its alert, the longest first */
        public array $overdue,
    ) {
    }
}
