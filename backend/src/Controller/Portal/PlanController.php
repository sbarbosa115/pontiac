<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Api\ApiResponse;
use App\Api\Output\CheckoutOutput;
use App\Api\Output\PortalPlanOutput;
use App\Enum\AccountFeature;
use App\Portal\PortalPlans;
use App\Payment\Checkout;
use App\Repository\EnrollmentRepository;
use App\Security\RequiresFeature;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** Mis planes: every plan of theirs, and paying one the consultant assigned them. */
#[Route('/api/portal', name: 'api_portal_plan_')]
#[RequiresFeature(AccountFeature::Portal)]
final class PlanController extends PortalController
{
    #[Route('/plans', name: 'list', methods: ['GET'])]
    #[ApiResponse(PortalPlanOutput::class, list: true, key: 'items')]
    public function list(PortalPlans $plans): JsonResponse
    {
        return $this->json(['items' => $plans->of($this->account(), $this->contact())]);
    }

    /** "Pagar": Wompi's checkout for a plan waiting for its payment; Wompi brings them back to the result page. */
    #[Route('/plans/{id}/pay', name: 'pay', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(CheckoutOutput::class)]
    public function pay(string $id, EnrollmentRepository $enrollments, Checkout $checkout): JsonResponse
    {
        $enrollment = $this->found($enrollments->findOneById($id));
        $this->own($enrollment->getContact());

        return $this->json(new CheckoutOutput(checkoutUrl: $checkout->start($this->account(), $enrollment)));
    }
}
