<?php

declare(strict_types=1);

namespace App\Tests\Functional\Portal;

use App\Entity\Account;
use App\Entity\BookingSession;
use App\Entity\Contact;
use App\Entity\Enrollment;
use App\Entity\Payment;
use App\Entity\Plan;
use App\Entity\SessionNote;
use App\Entity\User;
use App\Enum\AccountFeature;
use App\Enum\NoteVisibility;
use App\Enum\SessionStatus;
use App\Tests\Functional\Api\ApiTestCase;

/**
 * The client's portal: their next session, plans, payments and shared notes; booking a session of a plan they have,
 * moving or cancelling it like on the emailed link; paying a plan assigned to them. Everything is their own: another
 * person's ids answer 404, and private notes never show.
 */
final class PortalTest extends ApiTestCase
{
    private Account $account;
    private User $owner;
    private Contact $laura;
    private Plan $paid;
    private Enrollment $active;
    private BookingSession $past;
    private User $login;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->owner = $this->createOwner($this->account);
        $this->laura = $this->createContact($this->account);
        $this->paid = $this->createPlan($this->account, 'Plan A', '250000.00', 2, 60);
        // Plan A, paid, with its first session held.
        $this->active = new Enrollment($this->laura, $this->paid, null);
        $payment = Payment::manual($this->active, 'cash', null, $this->owner, new \DateTimeImmutable('-3 days'));
        $this->active->activate();
        [$this->past] = BookingSession::book($this->active, new \DateTimeImmutable('-2 days'), '', BookingSession::BOOKED_BY_STAFF);
        $this->past->close(SessionStatus::Done, new \DateTimeImmutable());
        $this->save(
            $this->active,
            $payment,
            $this->past,
            new SessionNote($this->past, $this->owner, 'Tarea: registrar gastos.', NoteVisibility::Shared),
            new SessionNote($this->past, $this->owner, 'Deuda alta.', NoteVisibility::Private),
        );
        $this->login = $this->createClientFor($this->laura);
    }

    public function testTheClientSeesTheirPlansNotesAndNextSession(): void
    {
        $this->actAs($this->login);

        $overview = $this->api('GET', '/api/portal/overview');
        self::assertNull($overview['nextSession']);
        self::assertSame(24, $overview['cancelHours']);
        self::assertSame([['Plan A', 'active', 2, 1, 1]], array_map(static fn (array $p) => [$p['planName'], $p['status'], $p['sessionsIncluded'], $p['sessionsTaken'], $p['sessionsUsed']], $overview['plans']));

        $plans = $this->api('GET', '/api/portal/plans')['items'];
        self::assertSame(['approved', 'cash'], [$plans[0]['payments'][0]['status'], $plans[0]['payments'][0]['method']]);
        self::assertSame(['Tarea: registrar gastos.'], array_column($this->api('GET', '/api/portal/notes')['items'], 'body'), 'the private note never shows');
        self::assertSame(['done'], array_column($this->api('GET', '/api/portal/sessions')['items'], 'status'));
    }

    public function testTheClientBooksMovesAndCancelsLikeOnTheEmailedLink(): void
    {
        $slots = $this->farSlots();
        $this->actAs($this->login);

        $days = $this->api('GET', '/api/portal/slots?enrollmentId='.$this->active->getId())['days'];
        self::assertNotEmpty($days);
        $session = $this->api('POST', '/api/portal/sessions', ['enrollmentId' => (string) $this->active->getId(), 'startsAt' => $slots[0]->format(\DATE_ATOM)]);
        self::assertSame(201, $this->responseStatus());
        self::assertSame(['scheduled', true], [$session['status'], $session['canChange']]);
        self::assertQueuedEmailCount(2, message: 'their confirmation and the consultant\'s notice');
        self::assertSame($session['id'], $this->api('GET', '/api/portal/overview')['nextSession']['id']);

        self::assertSame('no_sessions_left', $this->api('POST', '/api/portal/sessions', ['enrollmentId' => (string) $this->active->getId(), 'startsAt' => $slots[1]->format(\DATE_ATOM)])['error']);

        $moved = $this->api('POST', '/api/portal/sessions/'.$session['id'].'/reschedule', ['startsAt' => $slots[1]->format(\DATE_ATOM)]);
        self::assertSame($slots[1]->format(\DATE_ATOM), $moved['startsAt']);
        $cancelled = $this->api('POST', '/api/portal/sessions/'.$session['id'].'/cancel', ['reason' => 'Viaje']);
        self::assertSame(['cancelled', 'Viaje', false], [$cancelled['status'], $cancelled['cancelReason'], $cancelled['canChange']]);
    }

    public function testOnlyAFreeSlotAndOnlyBeforeTheLimit(): void
    {
        [$soon] = $this->bookSession($this->laura, $this->createPlan($this->account, 'Otro', '0'), new \DateTimeImmutable('+3 hours'));
        $this->actAs($this->login);

        // 03:00 is never one of the consultant's hours.
        $night = (new \DateTimeImmutable('+5 days', new \DateTimeZone('America/Bogota')))->setTime(3, 0);
        self::assertSame('slot_taken', $this->api('POST', '/api/portal/sessions', ['enrollmentId' => (string) $this->active->getId(), 'startsAt' => $night->format(\DATE_ATOM)])['error']);
        self::assertSame('too_late_to_change', $this->api('POST', '/api/portal/sessions/'.$soon->getId().'/cancel', [])['error']);
    }

    public function testTheClientPaysAPlanAssignedToThem(): void
    {
        $this->configureWompi($this->account);
        $pending = new Enrollment($this->laura, $this->paid, null);
        $this->save($pending);
        $this->actAs($this->login);

        $plan = array_values(array_filter($this->api('GET', '/api/portal/plans')['items'], static fn (array $p) => 'pending_payment' === $p['status']))[0];
        self::assertTrue($plan['payable']);
        $checkout = $this->api('POST', '/api/portal/plans/'.$plan['id'].'/pay');
        self::assertStringStartsWith('https://checkout.wompi.co/p/?', $checkout['checkoutUrl']);
        self::assertSame('enrollment_not_payable', $this->api('POST', '/api/portal/plans/'.$this->active->getId().'/pay')['error'], 'paid already');
    }

    public function testAnotherPersonsThingsAreNotFound(): void
    {
        $carlos = $this->createContact($this->account, 'Carlos', 'carlos@demo.test');
        [$his] = $this->bookSession($carlos, $this->paid, new \DateTimeImmutable('+5 days'));
        $this->save(new SessionNote($his, $this->owner, 'De Carlos', NoteVisibility::Shared));
        $this->actAs($this->login);

        self::assertNotContains('De Carlos', array_column($this->api('GET', '/api/portal/notes')['items'], 'body'));
        foreach ([['POST', '/api/portal/sessions/'.$his->getId().'/cancel'], ['POST', '/api/portal/sessions/'.$his->getId().'/reschedule', ['startsAt' => (new \DateTimeImmutable('+6 days'))->format(\DATE_ATOM)]], ['GET', '/api/portal/slots?sessionId='.$his->getId()], ['GET', '/api/portal/slots?enrollmentId='.$his->getEnrollment()->getId()], ['POST', '/api/portal/plans/'.$his->getEnrollment()->getId().'/pay']] as $call) {
            $this->api($call[0], $call[1], $call[2] ?? ('POST' === $call[0] ? [] : null));
            self::assertSame(404, $this->responseStatus(), $call[1]);
        }
    }

    public function testThePortalIsTheClientsAndTheAdminIsNot(): void
    {
        $this->actAs($this->login);
        $this->api('GET', '/api/admin/contacts');
        self::assertSame(403, $this->responseStatus());

        $this->actAs($this->owner);
        $this->api('GET', '/api/portal/overview');
        self::assertSame(403, $this->responseStatus());
    }

    public function testWithoutBookingTheClientStillSeesTheirPlans(): void
    {
        $this->save($this->account->setFeatures([AccountFeature::Portal, AccountFeature::Payments]));
        $this->actAs($this->login);

        $this->api('GET', '/api/portal/plans');
        self::assertSame(200, $this->responseStatus());
        self::assertSame('feature_disabled', $this->api('GET', '/api/portal/sessions')['error']);
    }

    /**
     * Two free slots far enough ahead for the client to still change them.
     *
     * @return list<\DateTimeImmutable>
     */
    private function farSlots(): array
    {
        return array_values(array_filter($this->freeSlots($this->account, 60), static fn (\DateTimeImmutable $s) => $s > new \DateTimeImmutable('+2 days')));
    }
}
