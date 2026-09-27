<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

/**
 * For support, a super admin acts as a consultant or an assistant (X-Switch-User: <user id>): the request runs as
 * that person, inside their account. Never as a client, never as another super admin, and nobody else can do it.
 */
final class ImpersonationTest extends ApiTestCase
{
    public function testTheSuperAdminPicksAmongActiveConsultantsAndAssistants(): void
    {
        $account = $this->createAccount();
        $this->createAssistant($account, 'beatriz@demo.test', 'Beatriz Bravo');
        $this->createOwner($account);
        $this->createClientLogin($account);
        $suspended = $this->createAccount('Plata Sana');
        $this->createOwner($suspended, 'suspendido@demo.test');
        $this->save($suspended->setActive(false));
        $this->actAs($this->createSuperAdmin());

        $items = $this->api('GET', '/api/platform/impersonatable-users')['items'];

        self::assertSame(['Andrés Asesor', 'Beatriz Bravo'], array_column($items, 'fullName'), 'owner first; no clients; no suspended account');
        self::assertSame(['owner', 'assistant'], array_column($items, 'role'));
        self::assertSame('Finanzas Claras', $items[0]['account']['name']);
    }

    public function testActingAsAConsultantWorksInTheirAccount(): void
    {
        $account = $this->createAccount();
        $owner = $this->createOwner($account);
        $this->createOwner($this->createAccount('Plata Sana'), 'otro@demo.test');
        $this->actAs($this->createSuperAdmin());
        $this->client->setServerParameter('HTTP_X_SWITCH_USER', (string) $owner->getId());

        $team = $this->api('GET', '/api/admin/team');

        self::assertSame(200, $this->responseStatus());
        self::assertSame(['Andrés Asesor'], array_column($team['items'], 'fullName'));
    }

    public function testNobodyActsAsAClientOrAnotherSuperAdmin(): void
    {
        $client = $this->createClientLogin($this->createAccount());
        $other = $this->createSuperAdmin('otra@pontiac.test');
        $this->actAs($this->createSuperAdmin());

        foreach ([$client, $other] as $target) {
            $this->client->setServerParameter('HTTP_X_SWITCH_USER', (string) $target->getId());
            $this->api('GET', '/api/me');
            self::assertSame(403, $this->responseStatus());
        }
    }

    public function testOnlyASuperAdminActsAsSomeone(): void
    {
        $account = $this->createAccount();
        $owner = $this->createOwner($account);
        $this->actAs($this->createAssistant($account));
        $this->client->setServerParameter('HTTP_X_SWITCH_USER', (string) $owner->getId());

        $this->api('GET', '/api/me');

        self::assertSame(403, $this->responseStatus());
    }
}
