<?php

declare(strict_types=1);

namespace App\Tests\Functional\Booking;

use App\Entity\Account;
use App\Entity\BookingSession;
use App\Entity\LandingPage;
use App\Entity\Plan;
use App\Enum\AccountFeature;
use App\Enum\SessionStatus;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Component\Mime\Email;

/**
 * A visitor books a free session from a page's Reserva section, then moves or cancels it from the emailed link until
 * the consultant's cancellation limit. Both sides hear about every change, with a calendar file.
 */
final class PublicBookingTest extends ApiTestCase
{
    private Account $account;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->createOwner($this->account);
        $this->plan = $this->createPlan($this->account, 'Diagnóstico gratuito');
    }

    public function testAVisitorBooksAFreeSlotAndBothSidesAreTold(): void
    {
        $this->bookingPage();
        $slot = $this->freeSlots($this->account)[0];

        $crawler = $this->client->request('GET', '/finanzas-claras/diagnostico');
        self::assertSelectorExists('#reserva form[action="/finanzas-claras/diagnostico/reservar"]');
        self::assertCount(1, $crawler->filter(sprintf('input[name="slot"][value="%s"]', $slot->format(\DATE_ATOM))), 'the first free slot is offered');
        // A date picker: the first open day chosen, the days with nothing free shown but not choosable.
        $day = $slot->setTimezone(new \DateTimeZone('America/Bogota'))->format('Y-m-d');
        self::assertSame($day, $crawler->filter('#cal-reserva .cal-grid input[type="radio"]:checked')->attr('value'));
        self::assertGreaterThan(0, $crawler->filter('#cal-reserva .cal-off')->count(), 'weekends have no hours');
        self::assertCount(1, $crawler->filter(sprintf('#cal-reserva-t%s input[name="slot"][value="%s"]', $day, $slot->format(\DATE_ATOM))), 'the time is under its day');
        self::assertStringContainsString('private', (string) $this->client->getResponse()->headers->get('Cache-Control'), 'free slots change: never cached');

        $this->book($slot);

        self::assertResponseRedirects('/finanzas-claras/diagnostico?reservado=1#reserva', 303);
        [$toVisitor, $toOwner] = $this->emailsTo('laura@demo.test', 'asesor@demo.test');
        self::assertStringStartsWith('Tu sesión con Finanzas Claras: ', (string) $toVisitor->getSubject());
        self::assertMatchesRegularExpression('#http://localhost:8080/finanzas-claras/reservar/[\w-]{20,64}#', (string) $toVisitor->getTextBody());
        $ics = $this->calendarFile($toVisitor);
        self::assertStringContainsString('METHOD:PUBLISH', $ics);
        self::assertStringContainsString('DTSTART:'.$slot->format('Ymd\THis\Z'), $ics);
        self::assertSame('Nueva sesión agendada: Laura Gómez', $toOwner->getSubject());

        $this->client->request('GET', '/finanzas-claras/diagnostico?reservado=1');
        self::assertSelectorTextContains('#reserva [role="status"]', 'Listo');

        $session = $this->onlySession();
        self::assertSame([SessionStatus::Scheduled, BookingSession::BOOKED_BY_VISITOR, 'Diagnóstico gratuito', 'Laura Gómez'], [$session->getStatus(), $session->getBookedBy(), $session->getEnrollment()->getPlanName(), $session->getContact()->getFullName()]);
        self::assertSame('diagnostico', $session->getEnrollment()->getSourcePage()?->getSlug());
    }

    public function testATakenSlotIsRefusedAndTheVisitorPicksAnother(): void
    {
        $this->bookingPage();
        $slot = $this->freeSlots($this->account)[0];
        $this->bookSession($this->createContact($this->account, 'Otra Persona', 'otra@demo.test'), $this->plan, $slot);

        $crawler = $this->book($slot);

        self::assertSame(409, $this->responseStatus());
        self::assertStringContainsString('Esa hora ya no está disponible', $crawler->filter('#reserva .error')->text());
        self::assertSame('Laura Gómez', $crawler->filter('#b-name')->attr('value'), 'what they typed is kept');
        self::assertCount(1, $crawler->filter('#cal-reserva .cal-grid input[type="radio"]:checked'), 'a day is chosen again');
        self::assertCount(1, $this->sessions());
    }

    public function testMistakesComeBackInSpanish(): void
    {
        $this->bookingPage();

        $crawler = $this->client->request('POST', '/finanzas-claras/diagnostico/reservar', $this->formData('no-es-correo', ['section' => 'reserva']));

        self::assertSame(422, $this->responseStatus());
        $errors = $crawler->filter('#reserva .error')->each(static fn ($node) => $node->text());
        self::assertContains('Elige un día y una hora.', $errors);
        self::assertCount(2, $errors, 'the slot and the email');
        self::assertSame([], $this->sessions());
    }

    public function testThePersonMovesAndThenCancelsFromTheirLink(): void
    {
        $this->bookingPage();
        [$first, $second] = $this->slotsAfterTheCancelLimit();
        $this->book($first);
        $token = $this->manageToken();

        $this->client->request('GET', '/finanzas-claras/reservar/'.$token);
        self::assertSame(200, $this->responseStatus());
        self::assertSelectorExists('meta[name="robots"][content*="noindex"]');
        self::assertSelectorExists(sprintf('input[name="slot"][value="%s"]', $second->format(\DATE_ATOM)));

        $this->client->request('POST', '/finanzas-claras/reservar/'.$token.'/cambiar', ['slot' => $second->format(\DATE_ATOM)]);
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/finanzas-claras/reservar/[\w-]+\?aviso=cambiada$#', $location);
        [$moved] = $this->emailsTo('laura@demo.test');
        self::assertSame('Tu sesión con Finanzas Claras cambió de hora', $moved->getSubject());
        self::assertEquals($second, $this->onlySession()->getStartsAt());

        $this->client->request('GET', '/finanzas-claras/reservar/'.$token);
        self::assertSame(404, $this->responseStatus(), 'the old link stops working');

        $newToken = (string) preg_replace('#^/finanzas-claras/reservar/([\w-]+)\?.*$#', '$1', $location);
        $this->client->request('POST', '/finanzas-claras/reservar/'.$newToken.'/cancelar', ['reason' => 'Me salió un viaje']);
        self::assertResponseRedirects('/finanzas-claras/reservar/'.$newToken.'?aviso=cancelada', 303);
        [$toVisitor, $toOwner] = $this->emailsTo('laura@demo.test', 'asesor@demo.test');
        self::assertSame('Tu sesión con Finanzas Claras fue cancelada', $toVisitor->getSubject());
        self::assertStringContainsString('METHOD:CANCEL', $this->calendarFile($toVisitor));
        self::assertSame('Sesión cancelada por el cliente: Laura Gómez', $toOwner->getSubject());

        $session = $this->onlySession();
        self::assertSame([SessionStatus::Cancelled, 'Me salió un viaje'], [$session->getStatus(), $session->getCancelReason()]);
        $this->client->request('GET', '/finanzas-claras/reservar/'.$newToken.'?aviso=cancelada');
        self::assertSelectorTextContains('[role="status"]', 'cancelada');
        self::assertSelectorNotExists('form');
    }

    public function testMovingASessionWorksBeforeTheConsultantEverSavedTheirHours(): void
    {
        // Their availability is made from the platform's defaults on first use; moving reads it twice in one request.
        $defaults = (new \App\Entity\Availability($this->account, new \App\Entity\PlatformSettings()));
        $slots = array_values(array_filter(
            static::getContainer()->get(\App\Booking\SlotFinder::class)->slots($this->account, $defaults, 45, new \DateTimeImmutable()),
            static fn (\DateTimeImmutable $s) => $s > new \DateTimeImmutable('+2 days'),
        ));
        [, $token] = $this->bookSession($this->createContact($this->account), $this->plan, $slots[0]);

        $this->client->request('POST', '/finanzas-claras/reservar/'.$token.'/cambiar', ['slot' => $slots[1]->format(\DATE_ATOM)]);

        self::assertSame(303, $this->responseStatus());
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM availability'));
    }

    public function testCloseToTheSessionItCannotBeChangedOnline(): void
    {
        // The limit is 24 hours by default; this one starts in three.
        [, $token] = $this->bookSession($this->createContact($this->account), $this->plan, new \DateTimeImmutable('+3 hours'));

        $this->client->request('GET', '/finanzas-claras/reservar/'.$token);
        self::assertSame(200, $this->responseStatus());
        self::assertSelectorNotExists('form');

        $this->client->request('POST', '/finanzas-claras/reservar/'.$token.'/cancelar');
        self::assertSame(409, $this->responseStatus());
        self::assertSelectorTextContains('[role="alert"]', 'Ya no es posible cambiar esta sesión en línea');
        self::assertSame(SessionStatus::Scheduled, $this->onlySession()->getStatus());
    }

    public function testAnotherConsultantsAddressDoesNotOpenTheSession(): void
    {
        $this->createAccount('Plata Sana');
        [, $token] = $this->bookSession($this->createContact($this->account), $this->plan, new \DateTimeImmutable('+3 days'));

        $this->client->request('GET', '/plata-sana/reservar/'.$token);
        self::assertSame(404, $this->responseStatus());
    }

    public function testWithoutAnActiveFreePlanOrTheFeatureThereIsNothingToBook(): void
    {
        $page = $this->bookingPage();
        $slot = $this->freeSlots($this->account)[0];

        $this->save($this->fresh($this->plan)->setActive(false));
        $this->client->request('GET', '/finanzas-claras/diagnostico');
        self::assertSelectorNotExists('#reserva');
        $this->book($slot);
        self::assertSame(404, $this->responseStatus());

        $this->save($this->fresh($this->plan)->setActive(true));
        $this->save($this->fresh($this->account)->setFeatures([AccountFeature::Portal]));
        $this->client->request('GET', '/finanzas-claras/'.$page->getSlug());
        self::assertSelectorNotExists('#reserva');
        $this->book($slot);
        self::assertSame(404, $this->responseStatus());
        self::assertSame([], $this->sessions());
    }

    private function bookingPage(): LandingPage
    {
        $planId = (string) $this->plan->getId();

        return $this->createPage($this->account, change: static function (array $content) use ($planId): array {
            foreach ($content['sections'] as &$section) {
                if ('booking' === $section['type']) {
                    $section['enabled'] = true;
                    $section['fields']['planId'] = $planId;
                }
            }

            return $content;
        });
    }

    private function book(\DateTimeImmutable $slot): \Symfony\Component\DomCrawler\Crawler
    {
        return $this->client->request('POST', '/finanzas-claras/diagnostico/reservar', $this->formData(extra: ['section' => 'reserva', 'slot' => $slot->format(\DATE_ATOM)]));
    }

    /**
     * Two free slots far enough ahead for the visitor to still change them.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function slotsAfterTheCancelLimit(): array
    {
        $later = array_values(array_filter($this->freeSlots($this->account), static fn (\DateTimeImmutable $s) => $s > new \DateTimeImmutable('+2 days')));

        return [$later[0], $later[1]];
    }

    /** The link in the confirmation just sent. */
    private function manageToken(): string
    {
        [$email] = $this->emailsTo('laura@demo.test');
        preg_match('#/reservar/([\w-]{20,64})#', (string) $email->getTextBody(), $match);

        return $match[1];
    }

    /**
     * The email the last request queued to each address, in that order.
     *
     * @return list<Email>
     */
    private function emailsTo(string ...$addresses): array
    {
        $emails = array_values(array_filter(self::getMailerMessages(), static fn ($m) => $m instanceof Email));

        return array_map(static function (string $address) use ($emails): Email {
            $to = array_values(array_filter($emails, static fn (Email $e) => $address === $e->getTo()[0]->getAddress()));
            self::assertCount(1, $to, 'one email to '.$address);

            return $to[0];
        }, $addresses);
    }

    private function calendarFile(Email $email): string
    {
        foreach ($email->getAttachments() as $attachment) {
            if ('sesion.ics' === $attachment->getFilename()) {
                return $attachment->getBody();
            }
        }
        self::fail('no calendar file attached');
    }

    /**
     * The same row, loaded again: a request in between leaves the test's copy detached.
     *
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

    /** @return list<BookingSession> */
    private function sessions(): array
    {
        $this->asPlatform();
        $this->em()->clear();

        return $this->em()->getRepository(BookingSession::class)->findAll();
    }

    private function onlySession(): BookingSession
    {
        $sessions = $this->sessions();
        self::assertCount(1, $sessions);

        return $sessions[0];
    }
}
