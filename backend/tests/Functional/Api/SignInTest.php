<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;

/**
 * Staff sign in with email and password at /api/login; clients at their consultant's portal (/api/portal-login), so
 * one email can be a client of two consultants. A disabled person or a suspended consultant is out at once, also
 * with a token issued before.
 */
final class SignInTest extends ApiTestCase
{
    public function testStaffSignInWithEmailAndPassword(): void
    {
        $owner = $this->createOwner($this->createAccount());

        $body = $this->api('POST', '/api/login', ['email' => 'ASESOR@demo.test ', 'password' => self::PASSWORD]);

        self::assertSame(200, $this->responseStatus());
        self::assertIsString($body['token']);
        self::assertNotNull($this->em()->getRepository(User::class)->find($owner->getId())?->getLastSignInAt(), 'the sign-in is recorded');
    }

    public function testAWrongPasswordIsRefused(): void
    {
        $this->createOwner($this->createAccount());

        $this->api('POST', '/api/login', ['email' => 'asesor@demo.test', 'password' => 'wrong-password-123']);

        self::assertSame(401, $this->responseStatus());
    }

    public function testAClientCannotSignInAsStaff(): void
    {
        $this->createClientLogin($this->createAccount());

        $this->api('POST', '/api/login', ['email' => 'cliente@demo.test', 'password' => self::PASSWORD]);

        self::assertSame(401, $this->responseStatus());
    }

    public function testADisabledPersonIsSignedOutAtTheirNextRequest(): void
    {
        $account = $this->createAccount();
        $assistant = $this->createAssistant($account);
        $this->actAs($assistant);
        $this->api('GET', '/api/me');
        self::assertSame(200, $this->responseStatus());

        $stored = $this->em()->getRepository(User::class)->find($assistant->getId());
        self::assertNotNull($stored);
        $this->save($stored->setActive(false));

        $this->api('GET', '/api/me');
        self::assertSame(401, $this->responseStatus(), 'the token they already had stops working');
        $this->signOut();
        $this->api('POST', '/api/login', ['email' => 'asistente@demo.test', 'password' => self::PASSWORD]);
        self::assertSame(401, $this->responseStatus());
    }

    public function testASuspendedConsultantsPeopleAreOut(): void
    {
        $account = $this->createAccount();
        $owner = $this->createOwner($account);
        $client = $this->createClientLogin($account);
        $this->save($account->setActive(false));

        $this->actAs($owner);
        $this->api('GET', '/api/me');
        self::assertSame(401, $this->responseStatus());

        $this->actAs($client);
        $this->api('GET', '/api/me');
        self::assertSame(401, $this->responseStatus());

        $this->signOut();
        $this->api('POST', '/api/portal-login', ['account' => $account->getSlug(), 'email' => 'cliente@demo.test', 'password' => self::PASSWORD]);
        self::assertSame(401, $this->responseStatus());
    }

    public function testAClientSignsInAtTheirConsultantsPortal(): void
    {
        $account = $this->createAccount();
        $client = $this->createClientLogin($account);

        $body = $this->api('POST', '/api/portal-login', ['account' => 'finanzas-claras', 'email' => 'Cliente@Demo.test', 'password' => self::PASSWORD]);

        self::assertSame(200, $this->responseStatus());
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$body['token']);
        $me = $this->api('GET', '/api/me');
        self::assertSame((string) $client->getId(), $me['id']);
        self::assertSame(['ROLE_CLIENT', 'ROLE_USER'], $me['roles']);
        self::assertSame('finanzas-claras', $me['account']['slug']);
    }

    public function testOneEmailIsAClientOfTwoConsultantsWithTwoLogins(): void
    {
        $first = $this->createAccount('Finanzas Claras');
        $second = $this->createAccount('Plata Sana');
        $atFirst = $this->createClientLogin($first, 'laura@demo.test');
        $atSecond = $this->createClientLogin($second, 'laura@demo.test');

        $token = $this->api('POST', '/api/portal-login', ['account' => 'plata-sana', 'email' => 'laura@demo.test', 'password' => self::PASSWORD])['token'];
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        self::assertSame((string) $atSecond->getId(), $this->api('GET', '/api/me')['id']);
        self::assertNotEquals($atFirst->getId(), $atSecond->getId());
    }

    public function testEveryPortalSignInFailureLooksTheSame(): void
    {
        $account = $this->createAccount();
        $this->createClientLogin($account);
        $this->createOwner($account);
        $disabled = $this->createClientLogin($account, 'inactivo@demo.test');
        $this->save($disabled->setActive(false));

        $attempts = [
            'unknown consultant' => ['account' => 'nadie', 'email' => 'cliente@demo.test', 'password' => self::PASSWORD],
            'unknown email' => ['account' => 'finanzas-claras', 'email' => 'otro@demo.test', 'password' => self::PASSWORD],
            'wrong password' => ['account' => 'finanzas-claras', 'email' => 'cliente@demo.test', 'password' => 'wrong-password-1'],
            'staff email' => ['account' => 'finanzas-claras', 'email' => 'asesor@demo.test', 'password' => self::PASSWORD],
            'disabled client' => ['account' => 'finanzas-claras', 'email' => 'inactivo@demo.test', 'password' => self::PASSWORD],
        ];
        foreach ($attempts as $case => $body) {
            $error = $this->api('POST', '/api/portal-login', $body);
            self::assertSame(401, $this->responseStatus(), $case);
            self::assertSame('invalid_credentials', $error['error'], $case);
        }
    }

    public function testThePortalSignInValidatesItsFields(): void
    {
        $error = $this->api('POST', '/api/portal-login', ['account' => 'finanzas-claras']);

        self::assertSame(422, $this->responseStatus());
        self::assertEqualsCanonicalizing(['email', 'password'], array_column($error['violations'], 'field'));
    }
}
