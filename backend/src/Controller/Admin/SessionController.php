<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\ApiValidationException;
use App\Api\Input\SessionCancelInput;
use App\Api\Input\SessionChangeInput;
use App\Api\Input\SessionCreateInput;
use App\Api\InputMapper;
use App\Api\Output\SessionOutput;
use App\Api\Presenter;
use App\Booking\Booker;
use App\Entity\BookingSession;
use App\Enum\AccountFeature;
use App\Enum\SessionStatus;
use App\Repository\BookingSessionRepository;
use App\Repository\ContactRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\PlanRepository;
use App\Security\RequiresFeature;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** Agenda: the consultant's sessions — the week, the list, and what happens to each. */
#[Route('/api/admin/sessions', name: 'api_admin_session_')]
#[RequiresFeature(AccountFeature::Booking)]
final class SessionController extends ApiController
{
    public function __construct(
        private readonly BookingSessionRepository $sessions,
        private readonly Booker $booker,
        private readonly InputMapper $input,
    ) {
    }

    /** ?q= searches the contact's name and email; ?status=; ?from= and ?to= (Y-m-d, local days, inclusive). */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(SessionOutput::class, page: true)]
    public function list(Request $request): JsonResponse
    {
        $page = $this->sessions->search(
            $request->query->getString('q'),
            $this->enumQuery($request->query->getString('status'), SessionStatus::class, 'status'),
            $this->localDay($request->query->getString('from'), 'from'),
            $this->localDay($request->query->getString('to'), 'to')?->modify('+1 day'),
            $this->pagination($request),
        );

        return $this->page($page, Presenter::session(...));
    }

    /** The scheduled sessions of the week that starts on ?start= (Y-m-d, local; this week's Monday by default). */
    #[Route('/week', name: 'week', methods: ['GET'])]
    #[ApiResponse(SessionOutput::class, list: true, key: 'items')]
    public function week(Request $request): JsonResponse
    {
        $start = $this->localDay($request->query->getString('start'), 'start')
            ?? (new \DateTimeImmutable('monday this week', new \DateTimeZone($this->account()->getTimezone())))->setTime(0, 0);

        return $this->json(['items' => array_map(Presenter::session(...), $this->sessions->findScheduledBetween($start, $start->modify('+7 days')))]);
    }

    /**
     * The consultant books a session for a contact: on a plan they have with sessions left (`enrollmentId`), or on an
     * active free plan (`planId`, a new enrollment).
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[ApiResponse(SessionOutput::class, status: 201)]
    public function create(Request $request, ContactRepository $contacts, PlanRepository $plans, EnrollmentRepository $enrollments): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), SessionCreateInput::class);
        $contact = $this->found($contacts->findOneById((string) $data->contactId));
        if (null !== $data->enrollmentId && '' !== $data->enrollmentId) {
            $enrollment = $this->found($enrollments->findOneById($data->enrollmentId));
            if (!$enrollment->getContact()->getId()->equals($contact->getId())) {
                throw ApiException::notFound();
            }
            $session = $this->booker->bookForEnrollment($this->account(), $enrollment, self::at((string) $data->startsAt));

            return $this->json(Presenter::session($session), 201);
        }
        if (null === $data->planId || '' === $data->planId) {
            throw ApiValidationException::single('planId', 'Choose a plan.');
        }
        $plan = $this->found($plans->findOneById($data->planId));
        $session = $this->booker->bookForContact($this->account(), $contact, $plan, self::at((string) $data->startsAt));

        return $this->json(Presenter::session($session), 201);
    }

    #[Route('/{id}/reschedule', name: 'reschedule', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(SessionOutput::class)]
    public function reschedule(string $id, Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), SessionChangeInput::class);
        $session = $this->load($id);
        $this->booker->reschedule($this->account(), $session, self::at((string) $data->startsAt), byVisitor: false);

        return $this->json(Presenter::session($session));
    }

    #[Route('/{id}/cancel', name: 'cancel', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(SessionOutput::class)]
    public function cancel(string $id, Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), SessionCancelInput::class);

        return $this->json(Presenter::session($this->booker->cancel($this->account(), $this->load($id), $data->reason, byVisitor: false)));
    }

    #[Route('/{id}/done', name: 'done', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(SessionOutput::class)]
    public function done(string $id): JsonResponse
    {
        return $this->json(Presenter::session($this->booker->close($this->load($id), SessionStatus::Done)));
    }

    #[Route('/{id}/no-show', name: 'no_show', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(SessionOutput::class)]
    public function noShow(string $id): JsonResponse
    {
        return $this->json(Presenter::session($this->booker->close($this->load($id), SessionStatus::NoShow)));
    }

    /** Undoes "done" or "no-show" marked by mistake. */
    #[Route('/{id}/reopen', name: 'reopen', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(SessionOutput::class)]
    public function reopen(string $id): JsonResponse
    {
        return $this->json(Presenter::session($this->booker->reopen($this->load($id))));
    }

    private function load(string $id): BookingSession
    {
        return $this->found($this->sessions->findOneById($id));
    }

    /** The start of a local day, as the API receives it (Y-m-d); empty: no bound. */
    private function localDay(string $value, string $parameter): ?\DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone($this->account()->getTimezone()));
        if (false === $day || $day->format('Y-m-d') !== $value) {
            throw ApiException::badRequest('invalid_filter', sprintf('"%s" must be a date (Y-m-d).', $parameter));
        }

        return $day;
    }

    private static function at(string $value): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat(\DATE_ATOM, $value) ?: throw ApiException::badRequest('invalid_time', 'Expected an ISO 8601 time.');
    }
}
