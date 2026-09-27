<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Output\AdminDashboardOutput;
use App\Repository\ContactRepository;
use App\Repository\LandingPageRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Inicio: what the consultant sees first. Each milestone adds what needs attention. */
final class DashboardController extends ApiController
{
    #[Route('/api/admin/dashboard', name: 'api_admin_dashboard', methods: ['GET'])]
    #[ApiResponse(AdminDashboardOutput::class)]
    public function __invoke(ContactRepository $contacts, LandingPageRepository $pages): JsonResponse
    {
        return $this->json(new AdminDashboardOutput(
            newLeadsLast7Days: $contacts->countCreatedSince(new \DateTimeImmutable('-7 days')),
            publishedPages: $pages->countPublished(),
            maxPublishedPages: $this->account()->getMaxPublishedPages(),
        ));
    }
}
