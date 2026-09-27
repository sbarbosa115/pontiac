<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Api\ApiResponse;
use App\Api\Output\PortalOverviewOutput;
use App\Api\Presenter;
use App\Booking\Booker;
use App\Enum\AccountFeature;
use App\Portal\PortalPlans;
use App\Enum\EnrollmentStatus;
use App\Enum\SessionStatus;
use App\Repository\AvailabilityRepository;
use App\Repository\BookingSessionRepository;
use App\Security\RequiresFeature;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Inicio: their next session, their plans, and until when they may change a session. */
#[Route('/api/portal/overview', name: 'api_portal_overview', methods: ['GET'])]
#[RequiresFeature(AccountFeature::Portal)]
final class OverviewController extends PortalController
{
    #[ApiResponse(PortalOverviewOutput::class)]
    public function __invoke(BookingSessionRepository $sessions, AvailabilityRepository $availability, Booker $booker, PortalPlans $plans): JsonResponse
    {
        $now = new \DateTimeImmutable();
        $next = null;
        foreach ($sessions->findForContact($this->contact()) as $session) {
            if (SessionStatus::Scheduled === $session->getStatus() && $session->getEndsAt() > $now && (null === $next || $session->getStartsAt() < $next->getStartsAt())) {
                $next = $session;
            }
        }
        $current = array_values(array_filter($plans->of($this->account(), $this->contact()), static fn ($p) => \in_array($p->status, [EnrollmentStatus::Active->value, EnrollmentStatus::PendingPayment->value], true)));

        return $this->json(new PortalOverviewOutput(
            nextSession: null === $next ? null : Presenter::portalSession($next, $booker->visitorMayChange($this->account(), $next)),
            plans: $current,
            cancelHours: $availability->forAccount($this->account())->getClientCancelHours(),
        ));
    }
}
