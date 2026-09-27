<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Input\AvailabilityInput;
use App\Api\InputMapper;
use App\Api\Output\AvailabilityOutput;
use App\Api\Output\SlotDayOutput;
use App\Api\Presenter;
use App\Booking\AvailabilityEditor;
use App\Booking\SlotFinder;
use App\Enum\AccountFeature;
use App\Repository\AvailabilityRepository;
use App\Repository\BookingSessionRepository;
use App\Repository\PlanRepository;
use App\Security\RequiresFeature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Agenda › Disponibilidad: when the consultant takes sessions, and the free slots that makes. */
#[Route('/api/admin/availability', name: 'api_admin_availability_')]
#[RequiresFeature(AccountFeature::Booking)]
final class AvailabilityController extends ApiController
{
    public function __construct(private readonly AvailabilityRepository $availability)
    {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    #[ApiResponse(AvailabilityOutput::class)]
    public function show(EntityManagerInterface $em): JsonResponse
    {
        $availability = $this->availability->forAccount($this->account());
        // The first read makes it from the platform's defaults: keep it.
        $em->flush();

        return $this->json(Presenter::availability($availability, $this->account()));
    }

    #[Route('', name: 'update', methods: ['PUT'])]
    #[ApiResponse(AvailabilityOutput::class)]
    public function update(Request $request, InputMapper $input, AvailabilityEditor $editor): JsonResponse
    {
        $data = $input->map($input->json($request), AvailabilityInput::class);
        $availability = $editor->apply($this->availability->forAccount($this->account()), $data);

        return $this->json(Presenter::availability($availability, $this->account()));
    }

    /**
     * The free slots for a plan's session length (?planId=), or to move a session (?sessionId=: its own time counts
     * as free), grouped by day.
     */
    #[Route('/slots', name: 'slots', methods: ['GET'])]
    #[ApiResponse(SlotDayOutput::class, list: true, key: 'days')]
    public function slots(Request $request, SlotFinder $finder, PlanRepository $plans, BookingSessionRepository $sessions): JsonResponse
    {
        $session = '' === $request->query->getString('sessionId') ? null : $this->found($sessions->findOneById($request->query->getString('sessionId')));
        $duration = null !== $session
            ? $session->getEnrollment()->getDurationMinutes()
            : $this->found($plans->findOneById($request->query->getString('planId')))->getDurationMinutes();
        $slots = $finder->slots($this->account(), $this->availability->forAccount($this->account()), $duration, new \DateTimeImmutable(), $session);

        return $this->json(['days' => Presenter::slotDays($this->account(), $slots)]);
    }
}
