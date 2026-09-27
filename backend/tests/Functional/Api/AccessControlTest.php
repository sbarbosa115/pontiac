<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Each role has its own URL space and no role inherits another: a consultant is not a super admin, a super admin
 * does not reach a consultant's data without acting as them, and a client reaches only the portal.
 */
final class AccessControlTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string, string, int}> [who, path, status]
     */
    public static function spaces(): iterable
    {
        yield 'owner in admin' => ['owner', '/api/admin/team', 200];
        yield 'assistant in admin' => ['assistant', '/api/admin/team', 200];
        yield 'client in admin' => ['client', '/api/admin/team', 403];
        yield 'super admin in admin' => ['superAdmin', '/api/admin/team', 403];

        yield 'owner in platform' => ['owner', '/api/platform/impersonatable-users', 403];
        yield 'assistant in platform' => ['assistant', '/api/platform/impersonatable-users', 403];
        yield 'client in platform' => ['client', '/api/platform/impersonatable-users', 403];
        yield 'super admin in platform' => ['superAdmin', '/api/platform/impersonatable-users', 200];

        yield 'anyone signed in reads themselves' => ['client', '/api/me', 200];
    }

    #[DataProvider('spaces')]
    public function testEachRoleReachesOnlyItsOwnSpace(string $who, string $path, int $status): void
    {
        $account = $this->createAccount();
        $users = [
            'owner' => fn (): User => $this->createOwner($account),
            'assistant' => fn (): User => $this->createAssistant($account),
            'client' => fn (): User => $this->createClientLogin($account),
            'superAdmin' => fn (): User => $this->createSuperAdmin(),
        ];
        $this->actAs($users[$who]());

        $this->api('GET', $path);

        self::assertSame($status, $this->responseStatus());
    }

    public function testTheApiNeedsASignIn(): void
    {
        foreach (['/api/me', '/api/admin/team', '/api/platform/impersonatable-users'] as $path) {
            $error = $this->api('GET', $path);
            self::assertSame(401, $this->responseStatus(), $path);
            self::assertSame('unauthorized', $error['error'] ?? null, $path);
        }
    }

    public function testErrorsAreJsonWithAStableCode(): void
    {
        $this->actAs($this->createOwner($this->createAccount()));

        $error = $this->api('GET', '/api/admin/nothing-here');

        self::assertSame(404, $this->responseStatus());
        self::assertSame('not_found', $error['error']);
    }
}
