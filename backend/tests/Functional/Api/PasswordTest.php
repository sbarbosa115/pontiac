<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

/**
 * "¿Olvidaste tu contraseña?" for staff and clients (an emailed link, one hour, once), and changing one's own password.
 * Asking never tells whether an email has an account.
 */
final class PasswordTest extends ApiTestCase
{
    public function testAConsultantResetsTheirPasswordFromTheEmailedLink(): void
    {
        $this->createOwner($this->createAccount());

        $this->api('POST', '/api/password-reset/request', ['email' => 'ASESOR@demo.test']);
        self::assertSame(204, $this->responseStatus());
        $token = $this->linkIn($this->onlyEmail('asesor@demo.test'));

        self::assertSame(['loginPath' => '/login'], $this->api('POST', '/api/password-reset/confirm', ['token' => $token, 'password' => 'una-nueva-clave-456']));
        $this->api('POST', '/api/password-reset/confirm', ['token' => $token, 'password' => 'otra-nueva-clave-789']);
        self::assertSame(404, $this->responseStatus(), 'the link works once');

        $this->api('POST', '/api/login', ['email' => 'asesor@demo.test', 'password' => self::PASSWORD]);
        self::assertSame(401, $this->responseStatus());
        self::assertArrayHasKey('token', $this->api('POST', '/api/login', ['email' => 'asesor@demo.test', 'password' => 'una-nueva-clave-456']));
    }

    public function testAClientAsksFromTheirConsultantsPortal(): void
    {
        $account = $this->createAccount();
        $this->createClientFor($this->createContact($account));

        $this->api('POST', '/api/password-reset/request', ['email' => 'laura@demo.test']);
        self::assertSame([], $this->emails(), 'staff sign-in knows no clients');

        $this->api('POST', '/api/password-reset/request', ['email' => 'laura@demo.test', 'account' => 'finanzas-claras']);
        $email = $this->onlyEmail('laura@demo.test');
        self::assertSame('Finanzas Claras', $email->getFrom()[0]->getName());
        self::assertSame(['loginPath' => '/finanzas-claras/portal/ingresar'], $this->api('POST', '/api/password-reset/confirm', ['token' => $this->linkIn($email), 'password' => 'una-nueva-clave-456']));
    }

    public function testAnUnknownEmailGetsTheSameAnswerAndNoEmail(): void
    {
        $account = $this->createAccount();
        $invited = User::createAssistant($account, 'nueva@demo.test', 'Nueva');
        $invited->issueInvitation();
        $this->save($invited);

        foreach (['nadie@demo.test', 'nueva@demo.test'] as $email) {
            $this->api('POST', '/api/password-reset/request', ['email' => $email]);
            self::assertSame(204, $this->responseStatus());
            self::assertSame([], $this->emails(), $email.': no password to reset (an invited person uses their invitation)');
        }
    }

    public function testAnExpiredLinkNoLongerWorks(): void
    {
        $owner = $this->createOwner($this->createAccount());
        $token = $owner->issuePasswordReset(new \DateTimeImmutable('-2 hours'));
        $this->save($owner);

        $this->api('POST', '/api/password-reset/confirm', ['token' => $token, 'password' => 'una-nueva-clave-456']);
        self::assertSame(404, $this->responseStatus());
    }

    public function testSignedInPeopleChangeTheirPasswordKnowingTheCurrentOne(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es');
        $this->actAs($this->createClientFor($this->createContact($this->createAccount())));

        $error = $this->api('POST', '/api/me/password', ['currentPassword' => 'no-es-esta', 'newPassword' => 'corta']);
        self::assertSame(['newPassword'], array_column($error['violations'], 'field'), 'the new one first; then the current one is checked');
        $error = $this->api('POST', '/api/me/password', ['currentPassword' => 'no-es-esta', 'newPassword' => 'una-nueva-clave-456']);
        self::assertSame('Esa no es tu contraseña actual.', $error['violations'][0]['message']);
        $this->api('POST', '/api/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'una-nueva-clave-456']);
        self::assertSame(204, $this->responseStatus());

        $this->signOut();
        self::assertArrayHasKey('token', $this->api('POST', '/api/portal-login', ['account' => 'finanzas-claras', 'email' => 'laura@demo.test', 'password' => 'una-nueva-clave-456']));
    }

    /** @return list<Email> sent at once by the last request */
    private function emails(): array
    {
        return array_values(array_filter(array_map(static fn (MessageEvent $e) => $e->isQueued() ? null : $e->getMessage(), self::getMailerEvents()), static fn ($m) => $m instanceof Email));
    }

    private function onlyEmail(string $to): Email
    {
        $emails = $this->emails();
        self::assertCount(1, $emails);
        self::assertSame($to, $emails[0]->getTo()[0]->getAddress());
        self::assertSame('Cambia tu contraseña', $emails[0]->getSubject());

        return $emails[0];
    }

    private function linkIn(Email $email): string
    {
        self::assertSame(1, preg_match('#http://localhost:8080/restablecer\?token=([\w-]+)#', (string) $email->getTextBody(), $link));

        return $link[1];
    }
}
