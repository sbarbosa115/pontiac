<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Account;
use App\Tests\Functional\Api\ApiTestCase;

/**
 * Which paths the React app answers and which Symfony renders: the app for /login, /invitacion, /admin, /plataforma
 * and each consultant's /<slug>/portal; server-rendered HTML for Pontiac's page and each consultant's pages.
 */
final class PageTest extends ApiTestCase
{
    public function testTheAppAnswersItsOwnPaths(): void
    {
        $this->createAccount();

        foreach (['/login', '/invitacion?token=x', '/admin', '/admin/equipo', '/plataforma', '/finanzas-claras/portal', '/finanzas-claras/portal/sesiones'] as $path) {
            $this->client->request('GET', $path);
            self::assertSame(200, $this->responseStatus(), $path);
            self::assertSelectorExists('#app[data-controller]', $path);
        }
    }

    public function testAPortalOfNoActiveConsultantIsNotFound(): void
    {
        $suspended = $this->createAccount('Plata Sana');
        $this->save($suspended->setActive(false));

        foreach (['/nadie/portal', '/plata-sana/portal'] as $path) {
            $this->client->request('GET', $path);
            self::assertSame(404, $this->responseStatus(), $path);
        }
    }

    public function testPontiacsOwnPageIsServerRendered(): void
    {
        $this->client->request('GET', '/');

        self::assertSame(200, $this->responseStatus());
        self::assertSelectorTextContains('h1', 'Tus páginas, tu agenda y tus clientes');
        self::assertSelectorNotExists('#app');
    }

    public function testAConsultantsHomeIsServedUntilTheyAreSuspended(): void
    {
        $account = $this->createAccount();

        $this->client->request('GET', '/finanzas-claras');
        self::assertSame(200, $this->responseStatus());
        self::assertSelectorTextContains('h1', 'Finanzas Claras');
        self::assertSelectorExists('meta[name="robots"][content="noindex"]', 'nothing to index before the first page is published');

        $this->save($account->setActive(false));
        $this->client->request('GET', '/finanzas-claras');
        self::assertSame(404, $this->responseStatus());
        $this->client->request('GET', '/nadie');
        self::assertSame(404, $this->responseStatus());
    }

    public function testTheAppsOwnPathsCannotBeAConsultantsAddress(): void
    {
        foreach (['login', 'admin', 'plataforma', 'invitacion', 'api', 'portal'] as $slug) {
            self::assertTrue(Account::isReservedSlug($slug), $slug);
            self::assertTrue((new Account('X', $slug))->hasReservedSlug(), $slug);
        }
        self::assertFalse(Account::isReservedSlug('finanzas-claras'));
    }
}
