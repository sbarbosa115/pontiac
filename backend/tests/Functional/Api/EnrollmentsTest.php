<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\AccountFeature;

/**
 * Planes y pagos on a contact's page: assign a plan, pay it (a payment recorded by the owner here), book its
 * sessions, and once they are used, renew or finish. The team runs it; only the owner records money.
 */
final class EnrollmentsTest extends ApiTestCase
{
    private Account $account;
    private User $owner;
    private User $assistant;
    private Plan $free;
    private Plan $paid;
    private Contact $laura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->owner = $this->createOwner($this->account);
        $this->assistant = $this->createAssistant($this->account);
        $this->free = $this->createPlan($this->account, 'Diagnóstico', '0', 1, 45);
        $this->paid = $this->createPlan($this->account, 'Plan A', '250000.00', 2, 60);
        $this->laura = $this->createContact($this->account);
    }

    public function testAFreePlanStartsAtOnceAndAPaidOneWaitsForItsPayment(): void
    {
        $this->actAs($this->assistant);

        $detail = $this->assign($this->free);
        self::assertSame(201, $this->responseStatus());
        self::assertSame([['Diagnóstico', 'active', true, null]], $this->plans($detail));
        self::assertQueuedEmailCount(0);

        $detail = $this->assign($this->paid);
        self::assertSame(['Plan A', 'pending_payment', false], \array_slice($this->plans($detail)[0], 0, 3));
        self::assertMatchesRegularExpression('#^http://localhost:8080/finanzas-claras/pagar/[\w-]{20,}$#', (string) $detail['enrollments'][0]['paymentUrl']);
        self::assertSame(['amount' => '250000.00', 'currency' => 'COP'], $detail['enrollments'][0]['price']);
        self::assertSame('lead', $detail['status'], 'a plan assigned is not a plan paid');
        self::assertQueuedEmailCount(1);

        $this->api('POST', '/api/admin/enrollments/'.$detail['enrollments'][0]['id'].'/send-link');
        self::assertSame(200, $this->responseStatus());
        self::assertQueuedEmailCount(1, message: 'the link again');
    }

    public function testTheOwnerRecordsAPaymentReceivedAndThePersonBecomesAClient(): void
    {
        $this->actAs($this->owner);
        $enrollment = $this->assign($this->paid)['enrollments'][0]['id'];

        $detail = $this->api('POST', "/api/admin/enrollments/$enrollment/payments", ['method' => 'transfer', 'note' => 'Bancolombia, comprobante 123', 'paidOn' => '2026-09-25']);

        self::assertSame(201, $this->responseStatus());
        self::assertSame('client', $detail['status']);
        self::assertSame(['active', null], [$detail['enrollments'][0]['status'], $detail['enrollments'][0]['paymentUrl']]);
        $payment = $detail['enrollments'][0]['payments'][0];
        self::assertSame(['approved', 'transfer', true, 'Bancolombia, comprobante 123', 'Andrés Asesor'], [$payment['status'], $payment['method'], $payment['manual'], $payment['note'], $payment['recordedBy']]);
        self::assertStringStartsWith('2026-09-25T17:00:00', (string) $payment['paidAt'], 'noon in Bogotá');
        self::assertQueuedEmailCount(2, message: 'the receipt and the owner\'s notice');

        $this->api('POST', "/api/admin/enrollments/$enrollment/payments", ['method' => 'cash']);
        self::assertSame(409, $this->responseStatus(), 'paid already');
    }

    public function testTheAssistantDoesNotRecordMoney(): void
    {
        $this->actAs($this->assistant);
        $enrollment = $this->assign($this->paid)['enrollments'][0]['id'];

        $this->api('POST', "/api/admin/enrollments/$enrollment/payments", ['method' => 'cash']);
        self::assertSame(403, $this->responseStatus());
    }

    public function testMistakesComeBackInSpanish(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es');
        $this->actAs($this->owner);
        $enrollment = $this->assign($this->paid)['enrollments'][0]['id'];

        $error = $this->api('POST', "/api/admin/enrollments/$enrollment/payments", ['method' => 'bitcoin', 'paidOn' => '2026-02-30']);
        self::assertSame(['method' => 'Elige cómo se pagó.', 'paidOn' => 'Escribe una fecha válida.'], array_column($error['violations'], 'message', 'field'));
        $error = $this->api('POST', '/api/admin/contacts/'.$this->laura->getId().'/enrollments', []);
        self::assertSame('Elige un plan.', $error['violations'][0]['message']);
    }

    public function testOnlyAPlanWaitingForPaymentIsCancelled(): void
    {
        $this->actAs($this->assistant);
        $detail = $this->assign($this->paid);
        $pending = $detail['enrollments'][0]['id'];

        $detail = $this->api('POST', "/api/admin/enrollments/$pending/cancel");
        self::assertSame(['cancelled', null], [$detail['enrollments'][0]['status'], $detail['enrollments'][0]['paymentUrl']]);
        $free = $this->assign($this->free)['enrollments'][0]['id'];
        self::assertSame('enrollment_not_cancellable', $this->api('POST', "/api/admin/enrollments/$free/cancel")['error']);
    }

    public function testAPaidPlansSessionsAreBookedOncePaidAndUpToItsNumber(): void
    {
        $this->actAs($this->owner);
        $enrollment = $this->assign($this->paid)['enrollments'][0]['id'];
        $at = (new \DateTimeImmutable('+5 days'))->setTime(15, 0);
        $book = fn (\DateTimeImmutable $when) => $this->api('POST', '/api/admin/sessions', ['contactId' => (string) $this->laura->getId(), 'enrollmentId' => $enrollment, 'startsAt' => $when->format(\DATE_ATOM)]);

        self::assertSame('enrollment_not_active', $book($at)['error']);
        $this->api('POST', "/api/admin/enrollments/$enrollment/payments", ['method' => 'cash']);

        self::assertNotEmpty($this->api('GET', "/api/admin/availability/slots?enrollmentId=$enrollment")['days']);
        self::assertSame(['Plan A', 60], [$book($at)['planName'], $book($at->modify('+1 day'))['durationMinutes']]);
        self::assertSame('no_sessions_left', $book($at->modify('+2 days'))['error']);
        $plan = $this->api('GET', '/api/admin/contacts/'.$this->laura->getId())['enrollments'][0];
        self::assertSame([2, 0], [$plan['sessionsTaken'], $plan['sessionsUsed']]);
    }

    public function testAPlanUsedUpIsRenewedOrTheConsultancyFinished(): void
    {
        [$first] = $this->bookSession($this->laura, $this->free, new \DateTimeImmutable('-2 hours'));
        $this->actAs($this->owner);
        $enrollment = $this->api('GET', '/api/admin/contacts/'.$this->laura->getId())['enrollments'][0]['id'];
        self::assertSame('enrollment_not_completed', $this->api('POST', "/api/admin/enrollments/$enrollment/finish")['error']);

        $this->api('POST', '/api/admin/sessions/'.$first->getId().'/done');
        self::assertSame('completed', $this->plan($enrollment)['status']);
        $this->api('POST', '/api/admin/sessions/'.$first->getId().'/reopen');
        self::assertSame('active', $this->plan($enrollment)['status'], 'reopened by mistake');
        $this->api('POST', '/api/admin/sessions/'.$first->getId().'/no-show');
        self::assertSame('completed', $this->plan($enrollment)['status'], 'a no-show uses the session too');

        $detail = $this->api('POST', "/api/admin/enrollments/$enrollment/renew", ['planId' => (string) $this->paid->getId()]);
        self::assertSame([['Plan A', 'pending_payment', false], ['Diagnóstico', 'completed', true, 'renewed']], [\array_slice($this->plans($detail)[0], 0, 3), $this->plans($detail)[1]]);

        $renewed = $detail['enrollments'][0]['id'];
        $this->api('POST', "/api/admin/enrollments/$renewed/payments", ['method' => 'cash']);
        self::assertSame('client', $this->api('GET', '/api/admin/contacts/'.$this->laura->getId())['status']);
        self::assertSame('enrollment_not_completed', $this->api('POST', "/api/admin/enrollments/$enrollment/finish")['error'], 'renewed already');
    }

    public function testFinishingTheConsultancyAndComingBack(): void
    {
        [$session] = $this->bookSession($this->laura, $this->free, new \DateTimeImmutable('-2 hours'));
        $this->actAs($this->assistant);
        $this->api('POST', '/api/admin/sessions/'.$session->getId().'/done');
        $enrollment = $this->api('GET', '/api/admin/contacts/'.$this->laura->getId())['enrollments'][0]['id'];

        $detail = $this->api('POST', "/api/admin/enrollments/$enrollment/finish");
        self::assertSame(['finished', 'finished'], [$detail['status'], $detail['enrollments'][0]['outcome']]);
    }

    public function testWithoutThePaymentsFeatureOnlyFreePlansAreAssigned(): void
    {
        $this->save($this->account->setFeatures([AccountFeature::Booking]));
        $this->actAs($this->owner);

        $this->assign($this->free);
        self::assertSame(201, $this->responseStatus());
        self::assertSame('feature_disabled', $this->assign($this->paid)['error']);
    }

    public function testAnotherConsultantsPlansAndPeopleAreNotFound(): void
    {
        $other = $this->createAccount('Plata Sana');
        $theirPlan = $this->createPlan($other, 'Suyo', '100000.00');
        $theirContact = $this->createContact($other, 'Pedro', 'pedro@demo.test');
        [$theirSession] = $this->bookSession($theirContact, $this->createPlan($other), new \DateTimeImmutable('+3 days'));
        $theirEnrollment = (string) $theirSession->getEnrollment()->getId();
        $this->actAs($this->owner);

        $this->api('POST', '/api/admin/contacts/'.$this->laura->getId().'/enrollments', ['planId' => (string) $theirPlan->getId()]);
        self::assertSame(404, $this->responseStatus());
        $this->api('POST', '/api/admin/contacts/'.$theirContact->getId().'/enrollments', ['planId' => (string) $this->free->getId()]);
        self::assertSame(404, $this->responseStatus());
        foreach (['send-link' => null, 'cancel' => null, 'finish' => null, 'renew' => ['planId' => (string) $this->free->getId()], 'payments' => ['method' => 'cash']] as $action => $body) {
            $this->api('POST', "/api/admin/enrollments/$theirEnrollment/$action", $body);
            self::assertSame(404, $this->responseStatus(), $action);
        }
        $this->api('POST', '/api/admin/sessions', ['contactId' => (string) $this->laura->getId(), 'enrollmentId' => $theirEnrollment, 'startsAt' => (new \DateTimeImmutable('+4 days'))->format(\DATE_ATOM)]);
        self::assertSame(404, $this->responseStatus());
    }

    /**
     * @return array<string, mixed> the contact's page
     */
    private function assign(Plan $plan): array
    {
        return $this->api('POST', '/api/admin/contacts/'.$this->laura->getId().'/enrollments', ['planId' => (string) $plan->getId()]);
    }

    /**
     * @param array<string, mixed> $detail
     *
     * @return list<list<mixed>> [plan, status, free, outcome] newest first
     */
    private function plans(array $detail): array
    {
        return array_map(static fn (array $e) => [$e['planName'], $e['status'], $e['free'], $e['outcome']], $detail['enrollments']);
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(string $id): array
    {
        foreach ($this->api('GET', '/api/admin/contacts/'.$this->laura->getId())['enrollments'] as $enrollment) {
            if ($id === $enrollment['id']) {
                return $enrollment;
            }
        }
        self::fail('no such plan');
    }
}
