<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use App\Enum\UiTheme;

/**
 * /api/me is who the requests run as and what the UI needs to show them: the account's formatting settings, and the
 * theme of the person at the screen, saved on their login so it follows them to other devices.
 */
final class MeTest extends ApiTestCase
{
    public function testAConsultantGetsTheirAccountsSettings(): void
    {
        $this->actAs($this->createOwner($this->createAccount()));

        $me = $this->api('GET', '/api/me');

        self::assertSame(['ROLE_OWNER', 'ROLE_USER'], $me['roles']);
        self::assertSame(
            ['name' => 'Finanzas Claras', 'slug' => 'finanzas-claras', 'country' => 'CO', 'currency' => 'COP', 'locale' => 'es_CO', 'timezone' => 'America/Bogota', 'features' => ['booking', 'payments', 'portal', 'flows']],
            array_diff_key($me['account'], ['id' => true]),
        );
        self::assertSame('light', $me['uiTheme'], 'someone who never chose sees light');
        self::assertNull($me['impersonator']);
    }

    public function testASuperAdminHasNoAccount(): void
    {
        $this->actAs($this->createSuperAdmin());

        self::assertNull($this->api('GET', '/api/me')['account']);
    }

    public function testEveryRoleSavesItsThemeAndGetsItBack(): void
    {
        $account = $this->createAccount();
        $people = [
            'owner' => $this->createOwner($account),
            'assistant' => $this->createAssistant($account),
            'client' => $this->createClientLogin($account),
            'super admin' => $this->createSuperAdmin(),
        ];

        foreach ($people as $role => $user) {
            $this->actAs($user);
            self::assertSame(['uiTheme' => 'dark'], $this->api('PATCH', '/api/me/preferences', ['uiTheme' => 'dark']), $role);
            self::assertSame('dark', $this->api('GET', '/api/me')['uiTheme'], $role);
        }

        $this->actAs($people['client']);
        $this->api('PATCH', '/api/me/preferences', ['uiTheme' => 'system']);
        self::assertSame('system', $this->api('GET', '/api/me')['uiTheme']);
    }

    public function testOnlyTheThreeThemesAreAccepted(): void
    {
        $this->actAs($this->createOwner($this->createAccount()));

        $this->client->jsonRequest('PATCH', '/api/me/preferences', ['uiTheme' => 'purple'], ['HTTP_ACCEPT_LANGUAGE' => 'es']);
        self::assertSame(422, $this->responseStatus());
        $error = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame([['field' => 'uiTheme', 'message' => 'Elige claro, oscuro o según el dispositivo.']], $error['violations']);

        self::assertSame(['uiTheme' => 'light'], $this->api('PATCH', '/api/me/preferences', []), 'an empty change changes nothing');
    }

    public function testASuperAdminActingAsSomeoneKeepsTheirOwnTheme(): void
    {
        $owner = $this->createOwner($this->createAccount());
        $this->actAs($this->createSuperAdmin());
        $this->client->setServerParameter('HTTP_X_SWITCH_USER', (string) $owner->getId());

        $this->api('PATCH', '/api/me/preferences', ['uiTheme' => 'dark']);
        $me = $this->api('GET', '/api/me');

        self::assertSame('Andrés Asesor', $me['fullName'], 'still acting as the consultant');
        self::assertSame('dark', $me['uiTheme'], 'but the theme is the super admin\'s');
        self::assertSame('Paula Plataforma', $me['impersonator']['fullName']);
        $this->em()->clear();
        self::assertSame(UiTheme::Light, $this->em()->getRepository(User::class)->find($owner->getId())?->uiTheme(), 'the consultant never chose');
    }
}
