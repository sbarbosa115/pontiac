<?php

declare(strict_types=1);

namespace App\Api\Output;

/** What the super admin sees first (Plataforma › Inicio). */
final readonly class PlatformDashboardOutput
{
    public function __construct(
        public int $activeAccounts,
        public int $suspendedAccounts,
        /** Attempts to send an email that failed in the last 7 days. */
        public int $failedEmailsLast7Days,
        /** Queue messages that failed every retry and wait in the "failed" queue. */
        public int $failedJobs,
    ) {
    }
}
