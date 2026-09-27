<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Output\AdminDashboardOutput;
use App\Api\Output\MoneyOutput;
use App\Repository\BookingSessionRepository;
use App\Repository\ContactRepository;
use App\Repository\PaymentRepository;
use App\Repository\LandingPageRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Inicio: what the consultant sees first. Each milestone adds what needs attention. */
final class DashboardController extends ApiController
{
    #[Route('/api/admin/dashboard', name: 'api_admin_dashboard', methods: ['GET'])]
    #[ApiResponse(AdminDashboardOutput::class)]
    public function __invoke(ContactRepository $contacts, LandingPageRepository $pages, BookingSessionRepository $sessions, PaymentRepository $payments): JsonResponse
    {
        $today = (new \DateTimeImmutable('today', new \DateTimeZone($this->account()->getTimezone())));
        $paid = $payments->approvedSince(new \DateTimeImmutable('-7 days'));

        return $this->json(new AdminDashboardOutput(
            newLeadsLast7Days: $contacts->countCreatedSince(new \DateTimeImmutable('-7 days')),
            publishedPages: $pages->countPublished(),
            maxPublishedPages: $this->account()->getMaxPublishedPages(),
            sessionsToday: \count($sessions->findScheduledBetween($today, $today->modify('+1 day'))),
            sessionsTomorrow: \count($sessions->findScheduledBetween($today->modify('+1 day'), $today->modify('+2 days'))),
            paymentsLast7Days: $paid['count'],
            paidLast7Days: new MoneyOutput(amount: $paid['total'], currency: $this->account()->getCurrency()),
        ));
    }
}
