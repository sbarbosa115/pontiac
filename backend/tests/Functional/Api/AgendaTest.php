<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\AccountFeature;

/**
 * Agenda: the consultant's hours (Disponibilidad) and their sessions — booked for a contact, moved, cancelled, and
 * marked done or no-show once they have happened. The owner and the assistant run it alike.
 */
final class AgendaTest extends ApiTestCase
{
    private Account $account;
    private User $assistant;
    private Plan $free;
    private Contact $laura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->createOwner($this->account);
        $this->assistant = $this->createAssistant($this->account);
        $this->free = $this->createPlan($this->account, 'Diagnóstico', '0', 1, 45);
        $this->laura = $this->createContact($this->account);
    }

    public function testTheHoursStartFromThePlatformsDefaultsAndChange(): void
    {
        $this->actAs($this->assistant);

        $availability = $this->api('GET', '/api/admin/availability');
        self::assertCount(10, $availability['weeklyRules'], 'weekdays, morning and afternoon');
        self::assertSame([15, 12, 30, 24, [24, 1], 'America/Bogota'], [$availability['bufferMinutes'], $availability['minNoticeHours'], $availability['bookingWindowDays'], $availability['clientCancelHours'], $availability['reminderHours'], $availability['timezone']]);

        $saved = $this->api('PUT', '/api/admin/availability', [
            'weeklyRules' => [['weekday' => 2, 'from' => '14:00', 'to' => '18:00'], ['weekday' => 2, 'from' => '08:00', 'to' => '10:00']],
            'exceptions' => [['date' => '2026-12-24', 'from' => null, 'to' => null]],
            'bufferMinutes' => 0, 'minNoticeHours' => 2, 'bookingWindowDays' => 14, 'clientCancelHours' => 12, 'reminderHours' => [2, 48],
            'meetingLink' => 'https://meet.example/asesor',
        ]);
        self::assertSame(200, $this->responseStatus());
        self::assertSame(['08:00', '14:00'], array_column($saved['weeklyRules'], 'from'), 'kept in order');
        self::assertSame([48, 2], $saved['reminderHours'], 'largest first');
        self::assertSame('https://meet.example/asesor', $this->api('GET', '/api/admin/availability')['meetingLink']);

        $days = $this->api('GET', '/api/admin/availability/slots?planId='.$this->free->getId())['days'];
        self::assertNotEmpty($days);
        self::assertStringStartsWith('martes', $days[0]['label'], 'Tuesdays only');
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:00\+00:00$/', $days[0]['slots'][0]['startsAt']);
    }

    public function testMistakesInTheHoursComeBackWhereTheyAre(): void
    {
        $this->actAs($this->assistant);

        // The UI asks in Spanish.
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es');
        $error = $this->api('PUT', '/api/admin/availability', [
            'weeklyRules' => [
                ['weekday' => 9, 'from' => '09:00', 'to' => '10:00'],
                ['weekday' => 1, 'from' => '9am', 'to' => '10:00'],
                ['weekday' => 1, 'from' => '11:00', 'to' => '10:00'],
                ['weekday' => 3, 'from' => '09:00', 'to' => '12:00'],
                ['weekday' => 3, 'from' => '11:00', 'to' => '13:00'],
            ],
            'exceptions' => [['date' => '2026-02-30', 'from' => null, 'to' => null]],
            'bufferMinutes' => 0, 'minNoticeHours' => 0, 'bookingWindowDays' => 14, 'clientCancelHours' => 0, 'reminderHours' => [24],
            'meetingLink' => '',
        ]);

        self::assertSame(422, $this->responseStatus());
        self::assertSame([
            'weeklyRules[0].weekday' => 'Elige un día de la semana.',
            'weeklyRules[1].from' => 'Escribe la hora como HH:MM, p. ej. 09:00.',
            'weeklyRules[2].to' => 'El final debe ser después del inicio.',
            'weeklyRules' => 'Dos franjas del mismo día se cruzan.',
            'exceptions[0].date' => 'Escribe una fecha válida.',
        ], array_column($error['violations'], 'message', 'field'));
    }

    public function testTheTeamBooksMovesAndCancelsASession(): void
    {
        $this->actAs($this->assistant);
        $at = (new \DateTimeImmutable('+5 days'))->setTime(15, 0);

        $session = $this->api('POST', '/api/admin/sessions', ['contactId' => (string) $this->laura->getId(), 'planId' => (string) $this->free->getId(), 'startsAt' => $at->format(\DATE_ATOM)]);
        self::assertSame(201, $this->responseStatus());
        self::assertSame(['scheduled', 'staff', 'Diagnóstico', 45, 'Laura Gómez'], [$session['status'], $session['bookedBy'], $session['planName'], $session['durationMinutes'], $session['contact']['fullName']]);
        self::assertSame($at->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM), $session['startsAt']);
        self::assertQueuedEmailCount(2, message: 'the confirmation and the consultant\'s notice');

        // 15 minutes of buffer: a second session 30 minutes later overlaps.
        $this->api('POST', '/api/admin/sessions', ['contactId' => (string) $this->laura->getId(), 'planId' => (string) $this->free->getId(), 'startsAt' => $at->modify('+30 minutes')->format(\DATE_ATOM)]);
        self::assertSame(409, $this->responseStatus());

        $moved = $this->api('POST', '/api/admin/sessions/'.$session['id'].'/reschedule', ['startsAt' => $at->modify('+1 day')->format(\DATE_ATOM)]);
        self::assertSame($at->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM), $moved['startsAt']);

        $this->api('POST', '/api/admin/sessions/'.$session['id'].'/done');
        self::assertSame(409, $this->responseStatus(), 'it has not happened yet');
        self::assertSame('session_not_closable', $this->api('POST', '/api/admin/sessions/'.$session['id'].'/no-show')['error']);

        $cancelled = $this->api('POST', '/api/admin/sessions/'.$session['id'].'/cancel', ['reason' => 'El asesor está enfermo']);
        self::assertSame(['cancelled', 'El asesor está enfermo'], [$cancelled['status'], $cancelled['cancelReason']]);
        $this->api('POST', '/api/admin/sessions/'.$session['id'].'/reschedule', ['startsAt' => $at->format(\DATE_ATOM)]);
        self::assertSame(409, $this->responseStatus(), 'a cancelled session stays cancelled');
    }

    public function testAPaidPlanIsBookedAfterItsPayment(): void
    {
        $paid = $this->createPlan($this->account, 'Plan A', '250000.00', 2, 60);
        $this->actAs($this->assistant);

        $error = $this->api('POST', '/api/admin/sessions', ['contactId' => (string) $this->laura->getId(), 'planId' => (string) $paid->getId(), 'startsAt' => (new \DateTimeImmutable('+5 days'))->format(\DATE_ATOM)]);

        self::assertSame([422, 'plan_not_bookable'], [$this->responseStatus(), $error['error']]);
        // The UI asks in Spanish.
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es');
        $error = $this->api('POST', '/api/admin/sessions', ['contactId' => (string) $this->laura->getId(), 'planId' => (string) $this->free->getId(), 'startsAt' => 'mañana']);
        self::assertSame('Elige un día y una hora.', $error['violations'][0]['message']);
    }

    public function testASessionThatHappenedIsMarkedDoneOrNoShowAndCanBeReopened(): void
    {
        [$session] = $this->bookSession($this->laura, $this->free, new \DateTimeImmutable('-2 hours'));
        $this->actAs($this->assistant);
        $path = '/api/admin/sessions/'.$session->getId();

        self::assertSame('done', $this->api('POST', $path.'/done')['status']);
        self::assertSame('scheduled', $this->api('POST', $path.'/reopen')['status']);
        self::assertSame('no_show', $this->api('POST', $path.'/no-show')['status']);
        $this->api('POST', $path.'/reopen');
        self::assertSame('session_not_closed', $this->api('POST', $path.'/reopen')['error']);
    }

    public function testTheListFiltersAndTheWeekShowsWhatIsScheduled(): void
    {
        $carlos = $this->createContact($this->account, 'Carlos Ruiz', 'carlos@demo.test');
        $monday = (new \DateTimeImmutable('monday next week', new \DateTimeZone('America/Bogota')))->setTime(10, 0);
        $this->bookSession($this->laura, $this->free, $monday);
        [$cancelled] = $this->bookSession($carlos, $this->free, $monday->modify('+1 day'));
        $this->save($cancelled->cancel(null));
        $this->bookSession($carlos, $this->free, $monday->modify('+8 days'));
        $this->actAs($this->assistant);

        self::assertSame(3, $this->api('GET', '/api/admin/sessions')['total']);
        self::assertSame(['Carlos Ruiz'], array_column(array_column($this->api('GET', '/api/admin/sessions?status=cancelled')['items'], 'contact'), 'fullName'));
        self::assertSame(2, $this->api('GET', '/api/admin/sessions?q=carlos@')['total']);
        self::assertSame(2, $this->api('GET', '/api/admin/sessions?from='.$monday->format('Y-m-d').'&to='.$monday->modify('+1 day')->format('Y-m-d'))['total'], 'both days included');

        $week = $this->api('GET', '/api/admin/sessions/week?start='.$monday->format('Y-m-d'))['items'];
        self::assertSame(['Laura Gómez'], array_column(array_column($week, 'contact'), 'fullName'), 'scheduled only, this week only');

        $this->api('GET', '/api/admin/sessions?status=soon');
        self::assertSame(400, $this->responseStatus());
        $this->api('GET', '/api/admin/sessions?from=2026-13-01');
        self::assertSame(400, $this->responseStatus());
    }

    public function testTheContactShowsTheirSessions(): void
    {
        $this->bookSession($this->laura, $this->free, new \DateTimeImmutable('+3 days'));
        $this->actAs($this->assistant);

        $detail = $this->api('GET', '/api/admin/contacts/'.$this->laura->getId());

        self::assertSame(['Diagnóstico'], array_column($detail['sessions'], 'planName'));
    }

    public function testWithoutTheBookingFeatureTheAgendaIsClosed(): void
    {
        $this->save($this->account->setFeatures([AccountFeature::Portal]));
        $this->actAs($this->assistant);

        foreach (['/api/admin/sessions', '/api/admin/availability'] as $path) {
            self::assertSame('feature_disabled', $this->api('GET', $path)['error'], $path);
            self::assertSame(403, $this->responseStatus());
        }
    }

    public function testAClientCannotOpenTheAgenda(): void
    {
        $this->actAs($this->createClientLogin($this->account));

        $this->api('GET', '/api/admin/sessions');
        self::assertSame(403, $this->responseStatus());
    }

    public function testAnotherConsultantsSessionsAndContactsAreNotFound(): void
    {
        $other = $this->createAccount('Plata Sana');
        $theirContact = $this->createContact($other, 'Pedro', 'pedro@demo.test');
        [$theirs] = $this->bookSession($theirContact, $this->createPlan($other), new \DateTimeImmutable('-1 hour'));
        $this->actAs($this->assistant);

        self::assertSame(0, $this->api('GET', '/api/admin/sessions')['total']);
        foreach (['reschedule' => ['startsAt' => (new \DateTimeImmutable('+3 days'))->format(\DATE_ATOM)], 'cancel' => [], 'done' => null, 'reopen' => null] as $action => $body) {
            $this->api('POST', '/api/admin/sessions/'.$theirs->getId().'/'.$action, $body);
            self::assertSame(404, $this->responseStatus(), $action);
        }
        $this->api('GET', '/api/admin/availability/slots?sessionId='.$theirs->getId());
        self::assertSame(404, $this->responseStatus());
        $this->api('POST', '/api/admin/sessions', ['contactId' => (string) $theirContact->getId(), 'planId' => (string) $this->free->getId(), 'startsAt' => (new \DateTimeImmutable('+3 days'))->format(\DATE_ATOM)]);
        self::assertSame(404, $this->responseStatus());
    }
}
