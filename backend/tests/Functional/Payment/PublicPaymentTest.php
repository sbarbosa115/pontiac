<?php

declare(strict_types=1);

namespace App\Tests\Functional\Payment;

use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\Enrollment;
use App\Entity\LeadSubmission;
use App\Entity\Payment;
use App\Entity\Plan;
use App\Enum\AccountFeature;
use App\Enum\ContactStatus;
use App\Enum\EnrollmentStatus;
use App\Enum\PageTemplate;
use App\Enum\PaymentStatus;
use App\Payment\WompiSignature;
use App\Tests\Functional\Api\ApiTestCase;
use App\Tests\Support\FakeWompi;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

/**
 * A visitor pays for a plan from a page: our pending payment, Wompi's checkout with its signature, and the result —
 * from Wompi's signed event or from asking Wompi — checked against what we asked for. Paying makes them a client.
 */
final class PublicPaymentTest extends ApiTestCase
{
    private Account $account;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->createOwner($this->account);
        $this->plan = $this->createPlan($this->account, 'Plan A', '250000.00', 2, 60);
        $this->configureWompi($this->account);
        $planId = (string) $this->plan->getId();
        $this->createPage($this->account, 'plan', PageTemplate::PlanOffer, change: static function (array $content) use ($planId): array {
            foreach ($content['sections'] as &$section) {
                if ('payment' === $section['type']) {
                    $section['enabled'] = true;
                    $section['fields']['planIds'] = [$planId];
                }
            }

            return $content;
        });
    }

    public function testAVisitorIsSentToWompisCheckoutWithASignedPayment(): void
    {
        $crawler = $this->client->request('GET', '/finanzas-claras/plan');
        self::assertSelectorExists('#precios form[action="/finanzas-claras/plan/pagar#precios"]');
        self::assertStringContainsString('250.000', $crawler->filter('#precios .price-card-price')->text());
        $offers = array_values(array_filter(json_decode($crawler->filter('script[type="application/ld+json"]')->text(), true)['@graph'], static fn (array $n) => 'Offer' === $n['@type']));
        self::assertSame([['Plan A', '250000.00', 'COP']], array_map(static fn (array $o) => [$o['name'], $o['price'], $o['priceCurrency']], $offers));

        $this->pay();

        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertSame(303, $this->responseStatus());
        self::assertStringStartsWith('https://checkout.wompi.co/p/?', $location);
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
        $payment = $this->onlyPayment();
        self::assertSame([
            'public-key' => 'pub_test_llavepublica',
            'currency' => 'COP',
            'amount-in-cents' => '25000000',
            'reference' => $payment->getReference(),
            'signature:integrity' => WompiSignature::integrity($payment->getReference(), 25000000, 'COP', self::WOMPI_INTEGRITY_SECRET),
            'redirect-url' => 'http://localhost:8080/finanzas-claras/pago/'.$payment->getReference(),
            'customer-data:email' => 'laura@demo.test',
            'customer-data:full-name' => 'Laura Gómez',
        ], $query);

        self::assertSame(PaymentStatus::Pending, $payment->getStatus());
        self::assertSame(EnrollmentStatus::PendingPayment, $payment->getEnrollment()->getStatus());
        self::assertSame([ContactStatus::Lead, 'plan'], [$payment->getContact()->getStatus(), $payment->getEnrollment()->getSourcePage()?->getSlug()]);
        $submission = $this->em()->getRepository(LeadSubmission::class)->findAll()[0];
        self::assertSame('Pago iniciado', $submission->getAnswers()[0]['label']);
    }

    public function testWompisApprovedEventMakesThemAClientOnce(): void
    {
        $this->pay();
        $payment = $this->onlyPayment();

        $answer = $this->event($this->wompiEvent($this->wompiTransaction($payment)));

        self::assertSame(['status' => 'applied'], $answer);
        // The receipt, the consultant's notice and, a first paid plan, the portal invitation.
        self::assertEqualsCanonicalizing(['Recibimos tu pago: Plan A', 'Nuevo pago: Laura Gómez · Plan A', 'Finanzas Claras te dio acceso a tu portal de cliente'], $this->subjects());
        $payment = $this->onlyPayment();
        self::assertSame([PaymentStatus::Approved, 'CARD', 'tx-1'], [$payment->getStatus(), $payment->getMethod(), $payment->getWompiTransactionId()]);
        self::assertNotNull($payment->getPaidAt());
        self::assertSame(EnrollmentStatus::Active, $payment->getEnrollment()->getStatus());
        self::assertSame(ContactStatus::Client, $payment->getContact()->getStatus());

        // Wompi sends events again when unsure they arrived.
        self::assertSame(['status' => 'unchanged'], $this->event($this->wompiEvent($this->wompiTransaction($payment))));
        self::assertSame([], $this->subjects());
    }

    public function testAnEventNotSignedWithTheConsultantsSecretIsRejected(): void
    {
        $this->pay();
        $payment = $this->onlyPayment();

        $this->client->jsonRequest('POST', '/webhooks/wompi/'.$this->account->getId(), $this->wompiEvent($this->wompiTransaction($payment), 'test_events_de_otro'));

        self::assertSame(401, $this->responseStatus());
        self::assertSame(PaymentStatus::Pending, $this->onlyPayment()->getStatus());
    }

    public function testAnAmountThatIsNotOursApprovesNothing(): void
    {
        $this->pay();
        $payment = $this->onlyPayment();

        $this->event($this->wompiEvent($this->wompiTransaction($payment, amountInCents: 100)));

        $payment = $this->onlyPayment();
        self::assertSame(PaymentStatus::Error, $payment->getStatus());
        self::assertSame([EnrollmentStatus::PendingPayment, ContactStatus::Lead], [$payment->getEnrollment()->getStatus(), $payment->getContact()->getStatus()]);
    }

    public function testADeclinedPaymentCanBeTriedAgain(): void
    {
        $this->pay();
        $this->event($this->wompiEvent($this->wompiTransaction($this->onlyPayment(), 'DECLINED')));
        $payment = $this->onlyPayment();
        $retry = '/finanzas-claras/pagar/'.$payment->getEnrollment()->getPaymentToken();

        $this->client->request('GET', '/finanzas-claras/pago/'.$payment->getReference());

        self::assertSelectorTextContains('h1', 'El pago no se completó');
        self::assertSelectorExists('a[href="'.$retry.'"]');
    }

    public function testTheResultPageAsksWompiAndNeverTrustsTheUrl(): void
    {
        $this->pay();
        $payment = $this->onlyPayment();

        // Someone else's transaction in the URL changes nothing.
        FakeWompi::$transactions['tx-otro'] = ['reference' => 'PON-0000000000000000'] + $this->wompiTransaction($payment, id: 'tx-otro');
        $this->client->request('GET', '/finanzas-claras/pago/'.$payment->getReference().'?id=tx-otro');
        self::assertSelectorTextContains('h1', 'Estamos confirmando tu pago');
        self::assertSelectorExists('meta[http-equiv="refresh"]');

        // Wompi has it, still pending: we remember the transaction and wait.
        FakeWompi::$transactions['tx-1'] = $this->wompiTransaction($payment, 'PENDING');
        $this->client->request('GET', '/finanzas-claras/pago/'.$payment->getReference().'?id=tx-1');
        self::assertSelectorTextContains('h1', 'Estamos confirmando tu pago');
        self::assertSame('tx-1', $this->onlyPayment()->getWompiTransactionId());

        // Approved at Wompi: the next refresh (no id in the URL) finds out.
        FakeWompi::$transactions['tx-1'] = $this->wompiTransaction($payment);
        $this->client->request('GET', '/finanzas-claras/pago/'.$payment->getReference());
        self::assertSelectorTextContains('h1', '¡Pago aprobado!');
        self::assertSelectorNotExists('meta[http-equiv="refresh"]');
        self::assertSelectorExists('meta[name="robots"][content="noindex"]');
        self::assertSame(PaymentStatus::Approved, $this->onlyPayment()->getStatus());
        self::assertContains('https://sandbox.wompi.co/v1/transactions/tx-1', FakeWompi::$requests, 'test keys ask the sandbox');
    }

    public function testMistakesComeBackInSpanishWithWhatWasTyped(): void
    {
        $crawler = $this->client->request('POST', '/finanzas-claras/plan/pagar', $this->formData('laura@demo.test', ['section' => 'precios', 'planId' => 'otro', 'consent' => '']));

        self::assertSame(422, $this->responseStatus());
        $errors = $crawler->filter('#precios .error')->each(static fn ($n) => $n->text());
        self::assertContains('Elige uno de los planes.', $errors);
        self::assertCount(2, $errors, 'the plan and the consent');
        self::assertSame('laura@demo.test', $crawler->filter('#p-email')->attr('value'));
        self::assertSame([], $this->payments());
    }

    public function testWithoutWompiOrThePaymentsFeatureNothingIsSold(): void
    {
        $this->save($this->fresh($this->account)->setFeatures([AccountFeature::Booking]));

        $this->client->request('GET', '/finanzas-claras/plan');
        self::assertSelectorNotExists('#precios');
        $this->pay();
        self::assertSame(404, $this->responseStatus());
        self::assertSame([], $this->payments());
    }

    public function testThePaymentLinkOfAnAssignedPlan(): void
    {
        $contact = $this->createContact($this->account);
        $this->actAs($this->createAssistant($this->account));
        $detail = $this->api('POST', '/api/admin/contacts/'.$contact->getId().'/enrollments', ['planId' => (string) $this->plan->getId()]);
        $link = (string) $detail['enrollments'][0]['paymentUrl'];
        $email = $this->emailTo('laura@demo.test');
        self::assertSame('Tu plan con Finanzas Claras: Plan A', $email->getSubject());
        self::assertStringContainsString($link, (string) $email->getTextBody());
        $this->signOut();

        $path = (string) parse_url($link, \PHP_URL_PATH);
        $this->client->request('GET', $path);
        self::assertSelectorTextContains('h1', 'Plan A');
        self::assertSelectorTextContains('dl', '250.000');
        $this->client->request('POST', $path);
        self::assertStringStartsWith('https://checkout.wompi.co/p/?', (string) $this->client->getResponse()->headers->get('Location'));

        $this->event($this->wompiEvent($this->wompiTransaction($this->onlyPayment())));
        $this->client->request('GET', $path);
        self::assertSelectorTextContains('[role="status"]', 'Este plan ya está pagado');
        self::assertSelectorNotExists('form');
    }

    public function testAnotherConsultantsAddressDoesNotOpenAPayment(): void
    {
        $this->createAccount('Plata Sana');
        $this->pay();
        $payment = $this->onlyPayment();

        $this->client->request('GET', '/plata-sana/pago/'.$payment->getReference());
        self::assertSame(404, $this->responseStatus());
        $this->client->jsonRequest('POST', '/webhooks/wompi/'.$this->fresh($this->account)->getId(), []);
        self::assertSame(401, $this->responseStatus(), 'no signature, no reading');
    }

    private function pay(): void
    {
        $this->client->request('POST', '/finanzas-claras/plan/pagar', $this->formData(extra: ['section' => 'precios', 'planId' => (string) $this->plan->getId()]));
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    private function event(array $event): array
    {
        $answer = $this->api('POST', '/webhooks/wompi/'.$this->account->getId(), $event);
        self::assertSame(200, $this->responseStatus());

        return $answer;
    }

    /**
     * The subjects of the emails the last request queued or sent. From the events, not getMailerMessages(): that one
     * takes an email sent at once (the portal invitation) for the queued email before it, and drops that one.
     *
     * @return list<string>
     */
    private function subjects(): array
    {
        return array_values(array_filter(array_map(static fn (MessageEvent $e) => $e->getMessage() instanceof Email ? (string) $e->getMessage()->getSubject() : null, self::getMailerEvents())));
    }

    private function emailTo(string $address): Email
    {
        $emails = array_values(array_filter(self::getMailerMessages(), static fn ($m) => $m instanceof Email && $address === $m->getTo()[0]->getAddress()));
        self::assertCount(1, $emails);

        return $emails[0];
    }

    /** @return list<Payment> */
    private function payments(): array
    {
        $this->asPlatform();
        $this->em()->clear();

        return $this->em()->getRepository(Payment::class)->findAll();
    }

    private function onlyPayment(): Payment
    {
        $payments = $this->payments();
        self::assertCount(1, $payments);

        return $payments[0];
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function fresh(object $entity): object
    {
        $this->asPlatform();
        $this->em()->clear();

        return $this->em()->find($entity::class, $entity->getId()) ?? throw new \LogicException('Gone.');
    }
}
