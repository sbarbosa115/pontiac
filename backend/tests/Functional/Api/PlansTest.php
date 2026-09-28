<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

/**
 * Planes: what the consultant sells. The owner sets names and prices; the assistant reads them to book sessions.
 */
final class PlansTest extends ApiTestCase
{
    public function testTheOwnerCreatesChangesAndRetiresAPlan(): void
    {
        $this->actAs($this->createOwner($this->createAccount()));

        $plan = $this->api('POST', '/api/admin/plans', ['name' => ' Plan A ', 'description' => 'Dos sesiones.', 'price' => '250000', 'sessions' => 2, 'durationMinutes' => 60]);
        self::assertSame(201, $this->responseStatus());
        self::assertSame(['Plan A', ['amount' => '250000.00', 'currency' => 'COP'], false, 2, 60, true], [$plan['name'], $plan['price'], $plan['free'], $plan['sessions'], $plan['durationMinutes'], $plan['active']]);

        $free = $this->api('PUT', '/api/admin/plans/'.$plan['id'], ['name' => 'Diagnóstico', 'description' => '', 'price' => '0', 'sessions' => 1, 'durationMinutes' => 45]);
        self::assertSame(['Diagnóstico', '0.00', true], [$free['name'], $free['price']['amount'], $free['free']]);

        self::assertFalse($this->api('DELETE', '/api/admin/plans/'.$plan['id'])['active']);
        self::assertSame(0, $this->api('GET', '/api/admin/plans')['total'], 'retired plans leave the list');
        self::assertSame(1, $this->api('GET', '/api/admin/plans?includeInactive=1')['total']);
        self::assertTrue($this->api('POST', '/api/admin/plans/'.$plan['id'].'/enable')['active']);
    }

    public function testMistakesComeBackInSpanishUnderTheirField(): void
    {
        $this->actAs($this->createOwner($this->createAccount()));

        // The UI asks in Spanish.
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es');
        $error = $this->api('POST', '/api/admin/plans', ['name' => '', 'price' => '250.000,00', 'sessions' => 0, 'durationMinutes' => 5]);

        self::assertSame(422, $this->responseStatus());
        $messages = array_column($error['violations'], 'message', 'field');
        self::assertSame(['name', 'price', 'sessions', 'durationMinutes'], array_keys($messages));
        self::assertSame('Escribe un valor con máximo dos decimales, p. ej. "250000.00".', $messages['price']);
    }

    public function testTheAssistantReadsButDoesNotSetPrices(): void
    {
        $account = $this->createAccount();
        $plan = $this->createPlan($account, 'Plan A', '250000.00', 2, 60);
        $this->actAs($this->createAssistant($account));

        self::assertSame(['Plan A'], array_column($this->api('GET', '/api/admin/plans')['items'], 'name'));
        self::assertSame(['Plan A'], array_column($this->api('GET', '/api/admin/plans/all')['items'], 'name'));
        $this->api('POST', '/api/admin/plans', ['name' => 'X', 'description' => '', 'price' => '1', 'sessions' => 1, 'durationMinutes' => 30]);
        self::assertSame(403, $this->responseStatus());
        $this->api('PUT', '/api/admin/plans/'.$plan->getId(), ['name' => 'X', 'description' => '', 'price' => '1', 'sessions' => 1, 'durationMinutes' => 30]);
        self::assertSame(403, $this->responseStatus());
        $this->api('DELETE', '/api/admin/plans/'.$plan->getId());
        self::assertSame(403, $this->responseStatus());
    }

    public function testAnotherConsultantsPlansAreNotFound(): void
    {
        $theirs = $this->createPlan($this->createAccount('Plata Sana'));
        $this->actAs($this->createOwner($this->createAccount()));

        self::assertSame(0, $this->api('GET', '/api/admin/plans')['total']);
        $this->api('PUT', '/api/admin/plans/'.$theirs->getId(), ['name' => 'Mío', 'description' => '', 'price' => '0', 'sessions' => 1, 'durationMinutes' => 45]);
        self::assertSame(404, $this->responseStatus());
        $this->api('DELETE', '/api/admin/plans/'.$theirs->getId());
        self::assertSame(404, $this->responseStatus());
    }
}
