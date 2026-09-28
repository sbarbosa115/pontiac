<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use Symfony\Component\Mime\Email;

/**
 * Ajustes › Equipo: the consultant sees their team, invites assistants (who set their own password from the email),
 * and disables or enables them. Assistants see the team but change nothing. Nobody sees another consultant's team.
 */
final class TeamTest extends ApiTestCase
{
    public function testTheTeamIsTheOwnerFirstThenTheAssistants(): void
    {
        $account = $this->createAccount();
        $this->createAssistant($account, 'beatriz@demo.test', 'Beatriz Bravo');
        $owner = $this->createOwner($account, 'zoe@demo.test', 'Zoe Zapata');
        $this->createAssistant($account, 'alba@demo.test', 'Alba Arias');
        $this->createClientLogin($account);
        $this->createOwner($this->createAccount('Plata Sana'), 'otro@demo.test');
        $this->actAs($owner);

        $page = $this->api('GET', '/api/admin/team');

        self::assertSame(200, $this->responseStatus());
        self::assertSame(3, $page['total'], 'clients and other consultants are not the team');
        self::assertSame(['Zoe Zapata', 'Alba Arias', 'Beatriz Bravo'], array_column($page['items'], 'fullName'));
        self::assertSame(['owner', 'assistant', 'assistant'], array_column($page['items'], 'role'));
        self::assertSame(['id', 'email', 'fullName', 'role', 'active', 'loginStatus', 'lastSignInAt'], array_keys($page['items'][0]));
        self::assertSame(1, $page['page']);
        self::assertSame(25, $page['perPage']);
    }

    public function testTheOwnerInvitesAnAssistantWhoSetsTheirOwnPassword(): void
    {
        $account = $this->createAccount();
        $this->actAs($this->createOwner($account));

        $member = $this->api('POST', '/api/admin/team', ['fullName' => ' Sofía Asistente ', 'email' => 'Sofia@Demo.test']);

        self::assertSame(201, $this->responseStatus());
        self::assertSame(['assistant', 'invited', 'sofia@demo.test', 'Sofía Asistente'], [$member['role'], $member['loginStatus'], $member['email'], $member['fullName']]);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'to', 'sofia@demo.test');
        self::assertEmailHtmlBodyContains($email, 'Finanzas Claras te invitó a su equipo');
        self::assertSame(1, preg_match('#http://localhost:8080/invitacion\?token=([\w-]+)#', (string) $email->getTextBody(), $link));

        $this->signOut();
        $invitation = $this->api('POST', '/api/invitations/lookup', ['token' => $link[1]]);
        self::assertSame(['email' => 'sofia@demo.test', 'fullName' => 'Sofía Asistente', 'role' => 'assistant', 'accountName' => 'Finanzas Claras', 'accountSlug' => 'finanzas-claras'], $invitation);

        $this->client->jsonRequest('POST', '/api/invitations/accept', ['token' => $link[1], 'password' => 'short'], ['HTTP_ACCEPT_LANGUAGE' => 'es']);
        self::assertSame(422, $this->responseStatus());
        $error = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('password', $error['violations'][0]['field']);
        self::assertStringContainsString('demasiado corto', $error['violations'][0]['message'], 'validation messages follow the UI language');

        $this->api('POST', '/api/invitations/accept', ['token' => $link[1], 'password' => 'una-clave-larga-123']);
        self::assertSame(204, $this->responseStatus());
        $this->api('POST', '/api/invitations/accept', ['token' => $link[1], 'password' => 'otra-clave-larga-123']);
        self::assertSame(404, $this->responseStatus(), 'a link works once');

        self::assertArrayHasKey('token', $this->api('POST', '/api/login', ['email' => 'sofia@demo.test', 'password' => 'una-clave-larga-123']));
    }

    public function testAnExpiredInvitationIsNotFound(): void
    {
        $assistant = User::createAssistant($this->createAccount(), 'tarde@demo.test', 'Tomás Tarde');
        $token = $assistant->issueInvitation();
        (fn () => $this->invitationExpiresAt = new \DateTimeImmutable('-1 minute'))->call($assistant);
        $this->save($assistant);

        $this->api('POST', '/api/invitations/lookup', ['token' => $token]);

        self::assertSame(404, $this->responseStatus());
    }

    public function testAnEmailAlreadyOnPontiacCannotBeInvited(): void
    {
        $account = $this->createAccount();
        $this->createOwner($this->createAccount('Plata Sana'), 'ocupado@demo.test');
        $this->actAs($this->createOwner($account));

        $error = $this->api('POST', '/api/admin/team', ['fullName' => 'Ocupado', 'email' => 'ocupado@demo.test']);

        self::assertSame(409, $this->responseStatus());
        self::assertSame('email_in_use', $error['error']);
        self::assertEmailCount(0);
    }

    public function testAnInvitationNeedsANameAndAValidEmail(): void
    {
        $this->actAs($this->createOwner($this->createAccount()));

        $error = $this->api('POST', '/api/admin/team', ['fullName' => '', 'email' => 'no-es-un-correo']);

        self::assertSame(422, $this->responseStatus());
        self::assertEqualsCanonicalizing(['fullName', 'email'], array_column($error['violations'], 'field'));
    }

    public function testAnAssistantSeesTheTeamButChangesNothing(): void
    {
        $account = $this->createAccount();
        $owner = $this->createOwner($account);
        $this->actAs($this->createAssistant($account));

        self::assertSame(2, $this->api('GET', '/api/admin/team')['total']);
        $this->api('POST', '/api/admin/team', ['fullName' => 'Otra', 'email' => 'otra@demo.test']);
        self::assertSame(403, $this->responseStatus());
        $this->api('DELETE', '/api/admin/team/'.$owner->getId());
        self::assertSame(403, $this->responseStatus());
    }

    public function testTheOwnerDisablesAndEnablesAnAssistantButNotThemselves(): void
    {
        $account = $this->createAccount();
        $owner = $this->createOwner($account);
        $assistant = $this->createAssistant($account);
        $this->actAs($owner);

        self::assertFalse($this->api('DELETE', '/api/admin/team/'.$assistant->getId())['active']);
        self::assertTrue($this->api('POST', '/api/admin/team/'.$assistant->getId().'/enable')['active']);

        $error = $this->api('DELETE', '/api/admin/team/'.$owner->getId());
        self::assertSame(409, $this->responseStatus());
        self::assertSame('owner_cannot_be_disabled', $error['error']);
    }

    public function testAnInvitationIsSentAgainOnlyToSomeoneWithoutAPassword(): void
    {
        $account = $this->createAccount();
        $owner = $this->createOwner($account);
        $active = $this->createAssistant($account, 'activa@demo.test');
        $this->actAs($owner);
        $invited = $this->api('POST', '/api/admin/team', ['fullName' => 'Nuevo', 'email' => 'nuevo@demo.test']);

        $this->api('POST', '/api/admin/team/'.$invited['id'].'/resend-invitation');
        self::assertSame(200, $this->responseStatus());
        self::assertEmailCount(1, message: 'one email for this request');

        $error = $this->api('POST', '/api/admin/team/'.$active->getId().'/resend-invitation');
        self::assertSame(409, $this->responseStatus());
        self::assertSame('already_has_access', $error['error']);
    }

    public function testAnotherConsultantsTeamIsNotFound(): void
    {
        $theirs = $this->createAssistant($this->createAccount('Plata Sana'), 'suyo@demo.test');
        $account = $this->createAccount();
        $ownClient = $this->createClientLogin($account);
        $this->actAs($this->createOwner($account));

        foreach ([$theirs, $ownClient] as $user) {
            $this->api('DELETE', '/api/admin/team/'.$user->getId());
            self::assertSame(404, $this->responseStatus(), 'not 403: another consultant\'s ids do not exist here, and a client is not the team');
            $this->api('POST', '/api/admin/team/'.$user->getId().'/enable');
            self::assertSame(404, $this->responseStatus());
        }
        $this->api('DELETE', '/api/admin/team/not-a-uuid');
        self::assertSame(404, $this->responseStatus());
    }
}
