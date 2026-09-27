<?php

declare(strict_types=1);

namespace App\Controller\Platform;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Output\PlatformDashboardOutput;
use App\Repository\AccountRepository;
use App\Repository\OutgoingEmailRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Plataforma › Inicio: how the platform is doing, and what needs a look. */
final class DashboardController extends ApiController
{
    #[Route('/api/platform/dashboard', name: 'api_platform_dashboard', methods: ['GET'])]
    #[ApiResponse(PlatformDashboardOutput::class)]
    public function __invoke(AccountRepository $accounts, OutgoingEmailRepository $emails, Connection $connection): JsonResponse
    {
        $counts = $accounts->countByStatus();

        return $this->json(new PlatformDashboardOutput(
            activeAccounts: $counts['active'],
            suspendedAccounts: $counts['suspended'],
            failedEmailsLast7Days: $emails->countFailedSince(new \DateTimeImmutable('-7 days')),
            // The queue's own table: what failed every retry waits there for "messenger:failed:retry".
            failedJobs: (int) $connection->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed' AND delivered_at IS NULL"),
        ));
    }
}
