<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\Input\PortalBookInput;
use App\Api\Input\SessionCancelInput;
use App\Api\Input\SessionChangeInput;
use App\Api\InputMapper;
use App\Api\Output\PortalSessionOutput;
use App\Api\Output\SlotDayOutput;
use App\Api\Presenter;
use App\Booking\Booker;
use App\Booking\SlotFinder;
use App\Entity\BookingSession;
use App\Enum\AccountFeature;
use App\Repository\AvailabilityRepository;
use App\Repository\BookingSessionRepository;
use App\Repository\EnrollmentRepository;
use App\Security\RequiresFeature;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Sesiones: theirs, booked from their plans at the consultant's free slots, moved or cancelled until the cancellation
 * limit — the rules a visitor has on the emailed link.
 */
#[Route('/api/portal', name: 'api_portal_session_')]
#[RequiresFeature(AccountFeature::Portal)]
#[RequiresFeature(AccountFeature::Booking)]
final class SessionController extends PortalController
{
    public function __construct(
        private readonly BookingSessionRepository $sessions,
        private readonly Booker $booker,
        private readonly InputMapper $input,
    ) {
    }

    /** Every session of theirs, the latest first. */
    #[Route('/sessions', name: 'list', methods: ['GET'])]
    #[ApiResponse(PortalSessionOutput::class, list: true, key: 'items')]
    public function list(): JsonResponse
    {
        return $this->json(['items' => array_map($this->present(...), $this->sessions->findForContact($this->contact()))]);
    }

    /** The free slots for a session of one of their plans (?enrollmentId=), or to move one (?sessionId=). */
    #[Route('/slots', name: 'slots', methods: ['GET'])]
    #[ApiResponse(SlotDayOutput::class, list: true, key: 'days')]
    public function slots(Request $request, EnrollmentRepository $enrollments, SlotFinder $finder, AvailabilityRepository $availability): JsonResponse
    {
        $moving = null;
        if ('' !== $request->query->getString('sessionId')) {
            $moving = $this->load($request->query->getString('sessionId'));
            $duration = $moving->getEnrollment()->getDurationMinutes();
        } else {
            $enrollment = $this->found($enrollments->findOneById($request->query->getString('enrollmentId')));
            $this->own($enrollment->getContact());
            $duration = $enrollment->getDurationMinutes();
        }
        $slots = $finder->slots($this->account(), $availability->forAccount($this->account()), $duration, new \DateTimeImmutable(), $moving);

        return $this->json(['days' => Presenter::slotDays($this->account(), $slots)]);
    }

    #[Route('/sessions', name: 'book', methods: ['POST'])]
    #[ApiResponse(PortalSessionOutput::class, status: 201)]
    public function book(Request $request, EnrollmentRepository $enrollments): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), PortalBookInput::class);
        $enrollment = $this->found($enrollments->findOneById((string) $data->enrollmentId));
        $this->own($enrollment->getContact());
        $session = $this->booker->bookForEnrollment($this->account(), $enrollment, self::at((string) $data->startsAt), BookingSession::BOOKED_BY_CLIENT);

        return $this->json($this->present($session), 201);
    }

    #[Route('/sessions/{id}/reschedule', name: 'reschedule', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(PortalSessionOutput::class)]
    public function reschedule(string $id, Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), SessionChangeInput::class);
        $session = $this->load($id);
        $this->booker->reschedule($this->account(), $session, self::at((string) $data->startsAt), byVisitor: true);

        return $this->json($this->present($session));
    }

    #[Route('/sessions/{id}/cancel', name: 'cancel', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(PortalSessionOutput::class)]
    public function cancel(string $id, Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), SessionCancelInput::class);

        return $this->json($this->present($this->booker->cancel($this->account(), $this->load($id), $data->reason, byVisitor: true)));
    }

    private function load(string $id): BookingSession
    {
        $session = $this->found($this->sessions->findOneById($id));
        $this->own($session->getContact());

        return $session;
    }

    private function present(BookingSession $session): PortalSessionOutput
    {
        return Presenter::portalSession($session, $this->booker->visitorMayChange($this->account(), $session));
    }

    private static function at(string $value): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat(\DATE_ATOM, $value) ?: throw ApiException::badRequest('invalid_time', 'Expected an ISO 8601 time.');
    }
}
