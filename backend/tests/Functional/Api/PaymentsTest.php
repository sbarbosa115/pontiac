<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Enum\AccountFeature;

/** Pagos: every payment of the consultant, filtered by status and dates; the whole team reads it. */
final class PaymentsTest extends ApiTestCase
{
    public function testTheTeamSeesEveryPaymentAndFilters(): void
    {
        $account = $this->createAccount();
        $owner = $this->createOwner($account);
        $assistant = $this->createAssistant($account);
        $paid = $this->createPlan($account, 'Plan A', '250000.00', 2, 60);
        $laura = $this->createContact($account);
        $carlos = $this->createContact($account, 'Carlos Ruiz', 'carlos@demo.test');
        $other = $this->createAccount('Plata Sana');
        $this->createContact($other, 'Pedro', 'pedro@demo.test');
        $this->actAs($owner);
        foreach ([$laura, $carlos] as $contact) {
            $enrollment = $this->api('POST', '/api/admin/contacts/'.$contact->getId().'/enrollments', ['planId' => (string) $paid->getId()])['enrollments'][0]['id'];
            $this->api('POST', "/api/admin/enrollments/$enrollment/payments", ['method' => 'cash']);
        }

        $this->actAs($assistant);
        $list = $this->api('GET', '/api/admin/payments');
        self::assertSame(2, $list['total']);
        self::assertSame(['Carlos Ruiz', 'Laura Gómez'], array_map(static fn (array $p) => $p['contact']['fullName'], $list['items']), 'newest first');
        self::assertSame([['amount' => '250000.00', 'currency' => 'COP'], 'approved', 'cash', 'Plan A'], [$list['items'][0]['amount'], $list['items'][0]['status'], $list['items'][0]['method'], $list['items'][0]['planName']]);
        self::assertSame(0, $this->api('GET', '/api/admin/payments?status=pending')['total']);
        self::assertSame(2, $this->api('GET', '/api/admin/payments?from='.date('Y-m-d', strtotime('-1 day')))['total']);
        self::assertSame(0, $this->api('GET', '/api/admin/payments?to=2020-01-01')['total']);
        $this->api('GET', '/api/admin/payments?status=paid');
        self::assertSame(400, $this->responseStatus());
    }

    public function testWithoutThePaymentsFeatureThereIsNoPagos(): void
    {
        $account = $this->createAccount();
        $this->save($account->setFeatures([AccountFeature::Booking]));
        $this->actAs($this->createOwner($account));

        self::assertSame('feature_disabled', $this->api('GET', '/api/admin/payments')['error']);
    }
}
