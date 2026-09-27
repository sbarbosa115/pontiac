<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\ApiException;
use App\Booking\Booker;
use App\Booking\SessionTime;
use App\Booking\SlotFinder;
use App\Entity\Account;
use App\Entity\BookingSession;
use App\Entity\LandingPage;
use App\Entity\LeadSubmission;
use App\Enum\AccountFeature;
use App\Enum\PageStatus;
use App\Page\LeadIntake;
use App\Page\PageRenderer;
use App\Page\PublicResolver;
use App\Repository\AvailabilityRepository;
use App\Repository\BookingSessionRepository;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Booking from a consultant's page, and the page where the person manages their session (the emailed link). Plain
 * HTML forms, like the lead form; the consultant's booking feature must be on.
 */
final class BookingController extends AbstractController
{
    private const SLUG = Account::SLUG_PATTERN;

    public function __construct(
        private readonly PublicResolver $resolver,
        private readonly Booker $booker,
        private readonly PageRenderer $renderer,
        private readonly LeadIntake $intake,
        private readonly PlanRepository $plans,
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'limiter.lead_form')]
        private readonly RateLimiterFactoryInterface $limiter,
    ) {
    }

    #[Route('/{slug}/reservar', name: 'public_home_book', requirements: ['slug' => self::SLUG], methods: ['POST'], priority: -5)]
    public function bookHome(string $slug, Request $request): Response
    {
        $account = $this->resolver->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }

        return $this->book($account, $this->resolver->home() ?? throw $this->createNotFoundException(), $request);
    }

    #[Route('/{slug}/{page}/reservar', name: 'public_page_book', requirements: ['slug' => self::SLUG, 'page' => self::SLUG], methods: ['POST'], priority: -10)]
    public function bookPage(string $slug, string $page, Request $request): Response
    {
        $account = $this->resolver->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $found = $this->resolver->page($account, $page);

        return $found instanceof Response ? $found : $this->book($account, $found, $request);
    }

    /** A reload of the page a booking came back to with errors: back to the page. */
    #[Route('/{slug}/reservar', name: 'public_home_book_reload', requirements: ['slug' => self::SLUG], methods: ['GET'], priority: -5)]
    #[Route('/{slug}/{page}/reservar', name: 'public_page_book_reload', requirements: ['slug' => self::SLUG, 'page' => self::SLUG], methods: ['GET'], priority: -10)]
    public function bookReload(string $slug, ?string $page = null): Response
    {
        return new RedirectResponse('/'.$slug.(null === $page ? '' : '/'.$page).'#reserva', 303);
    }

    #[Route('/{slug}/reservar/{token}', name: 'public_booking', requirements: ['slug' => self::SLUG, 'token' => '[\w-]{20,64}'], methods: ['GET'], priority: 5)]
    public function manage(string $slug, string $token, Request $request, BookingSessionRepository $sessions, AvailabilityRepository $availability, SlotFinder $slots): Response
    {
        $account = $this->resolver->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $session = $sessions->findOneByManageToken($token) ?? throw $this->createNotFoundException();

        return $this->renderManage($account, $session, $token, $availability, $slots, $request->query->getString('aviso'));
    }

    #[Route('/{slug}/reservar/{token}/cambiar', name: 'public_booking_reschedule', requirements: ['slug' => self::SLUG, 'token' => '[\w-]{20,64}'], methods: ['POST'], priority: 5)]
    public function reschedule(string $slug, string $token, Request $request, BookingSessionRepository $sessions, AvailabilityRepository $availability, SlotFinder $slots): Response
    {
        $account = $this->resolver->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $session = $sessions->findOneByManageToken($token) ?? throw $this->createNotFoundException();
        $startsAt = self::slot($request->request->getString('slot'));
        if (null === $startsAt) {
            return $this->renderManage($account, $session, $token, $availability, $slots, error: 'Elige el nuevo día y la hora.', status: 422);
        }

        try {
            $newToken = $this->booker->reschedule($account, $session, $startsAt, byVisitor: true);
        } catch (ApiException $e) {
            return $this->renderManage($account, $session, $token, $availability, $slots, error: self::message($e->getErrorCode()), status: $e->getStatusCode());
        }

        // The link changed with the time: the new one opens the session from now on (it is also in the new email).
        return new RedirectResponse('/'.$account->getSlug().'/reservar/'.$newToken.'?aviso=cambiada', 303);
    }

    #[Route('/{slug}/reservar/{token}/cancelar', name: 'public_booking_cancel', requirements: ['slug' => self::SLUG, 'token' => '[\w-]{20,64}'], methods: ['POST'], priority: 5)]
    public function cancel(string $slug, string $token, Request $request, BookingSessionRepository $sessions, AvailabilityRepository $availability, SlotFinder $slots): Response
    {
        $account = $this->resolver->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $session = $sessions->findOneByManageToken($token) ?? throw $this->createNotFoundException();

        try {
            $this->booker->cancel($account, $session, mb_substr($request->request->getString('reason'), 0, 500), byVisitor: true);
        } catch (ApiException $e) {
            return $this->renderManage($account, $session, $token, $availability, $slots, error: self::message($e->getErrorCode()), status: $e->getStatusCode());
        }

        return new RedirectResponse('/'.$account->getSlug().'/reservar/'.$token.'?aviso=cancelada', 303);
    }

    private function book(Account $account, LandingPage $page, Request $request): Response
    {
        $content = $page->getPublished();
        if (PageStatus::Published !== $page->getStatus() || null === $content || !$account->hasFeature(AccountFeature::Booking)) {
            throw $this->createNotFoundException();
        }
        $section = null;
        foreach ($content['sections'] as $candidate) {
            if ('booking' === $candidate['type'] && $candidate['enabled'] && $candidate['id'] === $request->request->getString('section')) {
                $section = $candidate;
            }
        }
        $plan = null === $section ? null : $this->plans->findOneById((string) $section['fields']['planId']);
        if (null === $plan || !$plan->isActive() || !$plan->isFree()) {
            throw $this->createNotFoundException();
        }
        $done = new RedirectResponse('/'.$account->getSlug().($page->isHome() ? '' : '/'.$page->getSlug()).'?reservado=1#reserva', 303);

        $data = $request->request->all();
        if (!$this->intake->isHuman($data)) {
            return $done;
        }
        ['values' => $values, 'errors' => $errors] = $this->intake->checkPerson($data);
        $startsAt = self::slot($values['slot'] ?? '');
        if (null === $startsAt) {
            $errors = ['slot' => 'Elige un día y una hora.'] + $errors;
        }
        if ([] !== $errors) {
            return $this->renderer->render($account, $page, $content, status: 422, booking: ['values' => $values, 'errors' => $errors]);
        }
        if (!$this->limiter->create($page->getId().'|'.$request->getClientIp())->consume()->isAccepted()) {
            return $this->renderer->render($account, $page, $content, status: 429, booking: ['values' => $values, 'errors' => ['_form' => 'Recibimos muchas reservas seguidas. Espera unos minutos e inténtalo de nuevo.']]);
        }

        $contact = $this->intake->person($account, $page, $values);
        try {
            $session = $this->booker->bookFromPage($account, $page, $plan, $contact, $startsAt ?? throw new \LogicException('Checked above.'));
        } catch (ApiException $e) {
            $this->em->clear();

            return $this->renderer->render($account, $page, $content, status: $e->getStatusCode(), booking: ['values' => $values, 'errors' => ['slot' => self::message($e->getErrorCode())]]);
        }
        // Their history shows the booking like any form they sent.
        $this->em->persist(new LeadSubmission($contact, $page, [['key' => 'reserva', 'label' => 'Sesión reservada', 'value' => $plan->getName().' · '.SessionTime::dateTime($account, $session->getStartsAt())]], [], $request->headers->get('referer')));
        $this->em->flush();

        return $done;
    }

    private function renderManage(Account $account, BookingSession $session, string $token, AvailabilityRepository $availability, SlotFinder $slots, string $notice = '', string $error = '', int $status = 200): Response
    {
        $canChange = $this->booker->visitorMayChange($account, $session);
        $days = [];
        if ($canChange) {
            foreach ($slots->slots($account, $availability->forAccount($account), $session->getEnrollment()->getDurationMinutes(), new \DateTimeImmutable(), $session) as $slot) {
                if ($slot == $session->getStartsAt()) {
                    continue;
                }
                $days[SessionTime::date($account, $slot)][] = ['value' => $slot->format(\DATE_ATOM), 'label' => SessionTime::time($account, $slot)];
            }
        }

        return $this->render('public/booking_manage.html.twig', [
            'account' => $account,
            'session' => $session,
            'token' => $token,
            'date' => SessionTime::date($account, $session->getStartsAt()),
            'time' => SessionTime::time($account, $session->getStartsAt()),
            'canChange' => $canChange,
            'days' => array_map(static fn (string $label, array $s) => ['label' => $label, 'slots' => $s], array_keys($days), array_values($days)),
            'notice' => match ($notice) {
                'cambiada' => 'Listo: tu sesión quedó en la nueva hora. Te enviamos la confirmación.',
                'cancelada' => 'Tu sesión quedó cancelada. Le avisamos a '.$account->getName().'.',
                default => '',
            },
            'error' => $error,
        ], new Response(null, $status));
    }

    private static function slot(string $value): ?\DateTimeImmutable
    {
        $at = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $value);

        return false === $at ? null : $at;
    }

    private static function message(string $code): string
    {
        return match ($code) {
            'slot_taken' => 'Esa hora ya no está disponible. Elige otra.',
            'too_late_to_change' => 'Ya no es posible cambiar esta sesión en línea. Responde el correo de confirmación para escribirle a tu asesor.',
            default => 'Esta sesión ya no se puede cambiar.',
        };
    }
}
