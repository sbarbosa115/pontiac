<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\ApiException;
use App\Booking\SessionTime;
use App\Entity\Account;
use App\Entity\Enrollment;
use App\Entity\LandingPage;
use App\Entity\LeadSubmission;
use App\Entity\Payment;
use App\Enum\EnrollmentStatus;
use App\Enum\PageStatus;
use App\Enum\PaymentStatus;
use App\Page\LeadIntake;
use App\Page\PageRenderer;
use App\Page\PublicResolver;
use App\Payment\Checkout;
use App\Payment\MoneyText;
use App\Payment\PaymentApplier;
use App\Payment\WompiClient;
use App\Repository\EnrollmentRepository;
use App\Repository\PaymentRepository;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use App\Repository\WompiSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paying for a plan without signing in: from a page's payment section, or from a plan's payment link; both end at
 * Wompi's checkout. Wompi brings the person back to the result page, which asks Wompi for the result itself.
 */
final class PaymentController extends AbstractController
{
    private const SLUG = Account::SLUG_PATTERN;
    private const TOKEN = '[\w-]{20,64}';
    private const REFERENCE = 'PON-[0-9A-F]{16}';

    public function __construct(
        private readonly PublicResolver $resolver,
        private readonly Checkout $checkout,
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'limiter.payment_start')]
        private readonly RateLimiterFactoryInterface $limiter,
    ) {
    }

    #[Route('/{slug}/pagar', name: 'public_home_pay', requirements: ['slug' => self::SLUG], methods: ['POST'], priority: -5)]
    public function payHome(string $slug, Request $request, PageRenderer $renderer, LeadIntake $intake, PlanRepository $plans): Response
    {
        $account = $this->resolver->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }

        return $this->pay($account, $this->resolver->home() ?? throw $this->createNotFoundException(), $request, $renderer, $intake, $plans);
    }

    #[Route('/{slug}/{page}/pagar', name: 'public_page_pay', requirements: ['slug' => self::SLUG, 'page' => self::SLUG], methods: ['POST'], priority: -10)]
    public function payPage(string $slug, string $page, Request $request, PageRenderer $renderer, LeadIntake $intake, PlanRepository $plans): Response
    {
        $account = $this->resolver->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $found = $this->resolver->page($account, $page);

        return $found instanceof Response ? $found : $this->pay($account, $found, $request, $renderer, $intake, $plans);
    }

    /** A reload of the page a payment came back to with errors: back to the page. */
    #[Route('/{slug}/pagar', name: 'public_home_pay_reload', requirements: ['slug' => self::SLUG], methods: ['GET'], priority: -5)]
    #[Route('/{slug}/{page}/pagar', name: 'public_page_pay_reload', requirements: ['slug' => self::SLUG, 'page' => self::SLUG], methods: ['GET'], priority: -10)]
    public function payReload(string $slug, ?string $page = null): Response
    {
        return new RedirectResponse('/'.$slug.(null === $page ? '' : '/'.$page), 303);
    }

    /** A plan's payment link, as emailed when the consultant assigned it. */
    #[Route('/{slug}/pagar/{token}', name: 'public_payment_link', requirements: ['slug' => self::SLUG, 'token' => self::TOKEN], methods: ['GET', 'POST'], priority: 5)]
    public function link(string $slug, string $token, Request $request, EnrollmentRepository $enrollments): Response
    {
        $account = $this->resolver->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $enrollment = $enrollments->findOneByPaymentToken($token) ?? throw $this->createNotFoundException();

        $error = '';
        if ($request->isMethod('POST')) {
            if (!$this->limiter->create($request->getClientIp() ?? '')->consume()->isAccepted()) {
                $error = 'Recibimos muchos intentos seguidos. Espera unos minutos e inténtalo de nuevo.';
            } else {
                try {
                    return new RedirectResponse($this->checkout->start($account, $enrollment), 303);
                } catch (ApiException $e) {
                    $error = $this->message($e->getErrorCode(), $account);
                }
            }
        }

        return $this->render('public/payment_link.html.twig', [
            'account' => $account,
            'enrollment' => $enrollment,
            'token' => $token,
            'price' => MoneyText::format($account, $enrollment->getPrice(), $enrollment->getCurrency()),
            'payable' => EnrollmentStatus::PendingPayment === $enrollment->getStatus(),
            'available' => $this->checkout->isAvailable($account),
            'error' => $error,
        ], new Response(null, '' === $error ? 200 : 409));
    }

    /**
     * Where Wompi sends the person back (`?id=` its transaction). The URL proves nothing: while the payment is
     * pending, we ask Wompi for the transaction and apply what it says; the page refreshes itself until there is a
     * result.
     */
    #[Route('/{slug}/pago/{reference}', name: 'public_payment_result', requirements: ['slug' => self::SLUG, 'reference' => self::REFERENCE], methods: ['GET'], priority: 5)]
    public function result(string $slug, string $reference, Request $request, PaymentRepository $payments, WompiSettingsRepository $wompi, WompiClient $client, PaymentApplier $applier, UserRepository $users): Response
    {
        $account = $this->resolver->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $payment = $payments->findOneByReference($reference) ?? throw $this->createNotFoundException();

        $settings = $wompi->current();
        $transactionId = $request->query->getString('id') ?: $payment->getWompiTransactionId();
        if (PaymentStatus::Pending === $payment->getStatus() && null !== $transactionId && '' !== $transactionId && null !== $settings?->getMode()) {
            $transaction = $client->transaction($settings->getMode(), $transactionId);
            // Someone else's transaction id in the URL changes nothing.
            if (null !== $transaction && ($transaction['reference'] ?? null) === $payment->getReference()) {
                $applier->fromWompi($account, $payment, $transaction);
            }
        }

        return $this->render('public/payment_result.html.twig', [
            'account' => $account,
            'payment' => $payment,
            'amount' => MoneyText::format($account, $payment->getAmount(), $payment->getCurrency()),
            'paidOn' => null === $payment->getPaidAt() ? null : SessionTime::date($account, $payment->getPaidAt()),
            'retryUrl' => $this->retryUrl($account, $payment),
            // Someone who can sign in to the portal goes back there to book their sessions.
            'portalUrl' => null === $users->findClientOf($payment->getContact()) ? null : '/'.$account->getSlug().'/portal',
        ]);
    }

    private function pay(Account $account, LandingPage $page, Request $request, PageRenderer $renderer, LeadIntake $intake, PlanRepository $plans): Response
    {
        $content = $page->getPublished();
        if (PageStatus::Published !== $page->getStatus() || null === $content || !$this->checkout->isAvailable($account)) {
            throw $this->createNotFoundException();
        }
        $section = null;
        foreach ($content['sections'] as $candidate) {
            if ('payment' === $candidate['type'] && $candidate['enabled'] && $candidate['id'] === $request->request->getString('section')) {
                $section = $candidate;
            }
        }
        if (null === $section) {
            throw $this->createNotFoundException();
        }
        $back = new RedirectResponse('/'.$account->getSlug().($page->isHome() ? '' : '/'.$page->getSlug()).'#'.$section['id'], 303);

        $data = $request->request->all();
        if (!$intake->isHuman($data)) {
            return $back;
        }
        ['values' => $values, 'errors' => $errors] = $intake->checkPerson($data);
        $planId = $request->request->getString('planId');
        $values['planId'] = $planId;
        $plan = \in_array($planId, $section['fields']['planIds'], true) ? $plans->findOneById($planId) : null;
        if (null === $plan || !$plan->isActive() || $plan->isFree()) {
            $errors = ['planId' => 'Elige uno de los planes.'] + $errors;
        }
        if ([] !== $errors) {
            return $renderer->render($account, $page, $content, status: 422, payment: ['values' => $values, 'errors' => $errors]);
        }
        if (!$this->limiter->create($request->getClientIp() ?? '')->consume()->isAccepted()) {
            return $renderer->render($account, $page, $content, status: 429, payment: ['values' => $values, 'errors' => ['_form' => 'Recibimos muchos intentos seguidos. Espera unos minutos e inténtalo de nuevo.']]);
        }

        $contact = $intake->person($account, $page, $values);
        $enrollment = new Enrollment($contact, $plan ?? throw new \LogicException('Checked above.'), $page);
        $this->em->persist($enrollment);
        // Their history shows it like any form they sent.
        $this->em->persist(new LeadSubmission($contact, $page, [['key' => 'pago', 'label' => 'Pago iniciado', 'value' => $plan->getName().' · '.MoneyText::format($account, $plan->getPrice(), $plan->getCurrency())]], [], $request->headers->get('referer')));
        $this->em->flush();

        return new RedirectResponse($this->checkout->start($account, $enrollment), 303);
    }

    private function retryUrl(Account $account, Payment $payment): ?string
    {
        $enrollment = $payment->getEnrollment();
        $token = $enrollment->getPaymentToken();

        return EnrollmentStatus::PendingPayment === $enrollment->getStatus() && null !== $token ? '/'.$account->getSlug().'/pagar/'.$token : null;
    }

    private function message(string $code, Account $account): string
    {
        return match ($code) {
            'payments_not_configured' => sprintf('Por ahora no es posible pagar en línea. Escríbele a %s.', $account->getName()),
            default => 'Este plan ya no está esperando un pago.',
        };
    }
}
