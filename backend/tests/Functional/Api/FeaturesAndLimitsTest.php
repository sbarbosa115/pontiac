<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Enum\AccountFeature;

/**
 * What the super admin sets for a consultant, as the consultant and their clients meet it: the assistant limit, and
 * the client portal switched off. The features reach the UI through /api/me, which hides their menus.
 */
final class FeaturesAndLimitsTest extends ApiTestCase
{
    public function testAConsultantInvitesUpToTheirAssistantLimit(): void
    {
        $account = $this->createAccount();
        $account->setLimits(10, 1, 1024, 10);
        $owner = $this->createOwner($account);
        $first = $this->createAssistant($account);
        $this->actAs($owner);

        $error = $this->api('POST', '/api/admin/team', ['fullName' => 'Otra', 'email' => 'otra@demo.test']);
        self::assertSame(409, $this->responseStatus());
        self::assertSame('assistant_limit_reached', $error['error']);
        self::assertEmailCount(0);

        // A disabled assistant does not count: disabling one makes room, and enabling them again needs room.
        $this->api('DELETE', '/api/admin/team/'.$first->getId());
        $second = $this->api('POST', '/api/admin/team', ['fullName' => 'Otra', 'email' => 'otra@demo.test']);
        self::assertSame(201, $this->responseStatus());
        $this->api('POST', '/api/admin/team/'.$first->getId().'/enable');
        self::assertSame(409, $this->responseStatus());
        $this->api('DELETE', '/api/admin/team/'.$second['id']);
        $this->api('POST', '/api/admin/team/'.$first->getId().'/enable');
        self::assertSame(200, $this->responseStatus());
    }

    public function testWithThePortalOffClientsCannotSignInAndAreSignedOut(): void
    {
        $account = $this->createAccount();
        $client = $this->createClientLogin($account);
        $this->actAs($client);
        $this->api('GET', '/api/me');
        self::assertSame(200, $this->responseStatus());

        $this->em()->clear();
        $account = $this->em()->find($account::class, $account->getId()) ?? throw new \LogicException('Gone.');
        $this->save($account->setFeatures([AccountFeature::Booking]));

        $this->api('GET', '/api/me');
        self::assertSame(401, $this->responseStatus(), 'the session they had stops working');
        $this->signOut();
        $error = $this->api('POST', '/api/portal-login', ['account' => 'finanzas-claras', 'email' => 'cliente@demo.test', 'password' => self::PASSWORD]);
        self::assertSame(403, $this->responseStatus());
        self::assertSame('feature_disabled', $error['error']);
    }

    public function testTheUiLearnsTheFeaturesFromMe(): void
    {
        $account = $this->createAccount();
        $account->setFeatures([AccountFeature::Portal, AccountFeature::Booking]);
        $this->actAs($this->createOwner($account));

        self::assertSame(['booking', 'portal'], $this->api('GET', '/api/me')['account']['features']);
    }
}
