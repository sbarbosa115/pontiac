<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use App\Entity\OutgoingEmail;
use App\Mail\EmailLog;
use App\Mail\EmailTag;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Configuración › Administradores, Correos and Inicio: super admins invite each other; every email Pontiac tries to
 * send is logged, the tag headers never reach the recipient; the dashboard counts what needs a look.
 */
final class AdminsAndEmailsTest extends ApiTestCase
{
    public function testSuperAdminsInviteEachOtherButNobodyDisablesThemselves(): void
    {
        $me = $this->createSuperAdmin();
        $this->actAs($me);

        $invited = $this->api('POST', '/api/platform/admins', ['fullName' => 'Olga Operaciones', 'email' => 'olga@pontiac.test']);
        self::assertSame(201, $this->responseStatus());
        self::assertSame(['invited', false], [$invited['loginStatus'], $invited['you']]);
        self::assertEmailHtmlBodyContains(self::getMailerMessage() ?? throw new \LogicException('No email.'), 'Te invitaron a administrar Pontiac.');

        $list = $this->api('GET', '/api/platform/admins');
        self::assertSame(['Olga Operaciones', 'Paula Plataforma'], array_column($list['items'], 'fullName'));
        self::assertSame([false, true], array_column($list['items'], 'you'));

        self::assertFalse($this->api('DELETE', '/api/platform/admins/'.$invited['id'])['active']);
        self::assertTrue($this->api('POST', '/api/platform/admins/'.$invited['id'].'/enable')['active']);
        $error = $this->api('DELETE', '/api/platform/admins/'.$me->getId());
        self::assertSame(409, $this->responseStatus());
        self::assertSame('cannot_disable_yourself', $error['error']);

        $owner = $this->createOwner($this->createAccount());
        $this->api('DELETE', '/api/platform/admins/'.$owner->getId());
        self::assertSame(404, $this->responseStatus(), 'a consultant is not a super admin');
    }

    public function testEveryEmailSentIsLoggedWithTheConsultantItWasFor(): void
    {
        $account = $this->createAccount();
        $this->actAs($this->createOwner($account));
        $this->api('POST', '/api/admin/team', ['fullName' => 'Sofía', 'email' => 'sofia@demo.test']);
        $this->actAs($this->createSuperAdmin());
        $this->api('POST', '/api/platform/emails/test', ['to' => 'yo@pontiac.test']);
        self::assertSame(204, $this->responseStatus());

        $log = $this->api('GET', '/api/platform/emails');
        self::assertSame(2, $log['total']);
        [$test, $invitation] = $log['items'];
        self::assertSame(['test', 'yo@pontiac.test', 'sent', null, null], [$test['kind'], $test['recipient'], $test['status'], $test['error'], $test['account']]);
        self::assertSame(['invitation', 'sofia@demo.test', 'Finanzas Claras te invitó a Pontiac', 'Finanzas Claras'], [$invitation['kind'], $invitation['recipient'], $invitation['subject'], $invitation['account']['name']]);

        self::assertSame(1, $this->api('GET', '/api/platform/emails?account='.$account->getId())['total']);
        self::assertSame(0, $this->api('GET', '/api/platform/emails?status=failed')['total']);
        self::assertSame(0, $this->api('GET', '/api/platform/emails?account=not-a-uuid')['total']);
        $this->api('GET', '/api/platform/emails?status=lost');
        self::assertSame(400, $this->responseStatus());
    }

    public function testAQueuedEmailIsLoggedWhenTheWorkerSendsIt(): void
    {
        $account = $this->createAccount();
        $this->actAs($this->createSuperAdmin());
        // The mailer a queued email goes through, as the framework builds it (nothing in the app injects one yet).
        $container = static::getContainer();
        $mailer = new Mailer($container->get('mailer.transports'), $container->get(MessageBusInterface::class), $container->get('event_dispatcher'));

        $mailer->send(EmailTag::apply((new Email())->from('a@pontiac.test')->to('cliente@demo.test')->subject('Recordatorio')->text('x'), 'reminder', $account));
        self::assertSame(0, $this->em()->getRepository(OutgoingEmail::class)->count([]), 'queued, not sent yet');

        self::assertSame(1, $this->runWorker());
        $sent = array_values(array_filter(self::getMailerEvents(), static fn (MessageEvent $event) => !$event->isQueued()));
        self::assertCount(1, $sent, 'the worker sent it');
        $message = $sent[0]->getMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertFalse($message->getHeaders()->has(EmailTag::ACCOUNT_HEADER), 'the worker takes the tags off too');

        $log = $this->api('GET', '/api/platform/emails')['items'];
        self::assertSame([['reminder', 'Recordatorio', 'Finanzas Claras']], array_map(static fn (array $e) => [$e['kind'], $e['subject'], $e['account']['name'] ?? null], $log));
    }

    public function testTheTagHeadersNeverReachTheRecipient(): void
    {
        $this->actAs($this->createSuperAdmin());

        $this->api('POST', '/api/platform/emails/test', ['to' => 'yo@pontiac.test']);

        $sent = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $sent);
        self::assertFalse($sent->getHeaders()->has(EmailTag::KIND_HEADER));
        self::assertFalse($sent->getHeaders()->has(EmailTag::ACCOUNT_HEADER));
    }

    public function testAFailedSendIsLoggedWithTheServersReason(): void
    {
        $this->actAs($this->createSuperAdmin());
        $email = EmailTag::apply((new Email())->from('a@pontiac.test')->to('b@demo.test')->subject('Hola')->text('x'), EmailTag::TEST);
        /** @var EmailLog $log */
        $log = static::getContainer()->get(EmailLog::class);

        // What the mailer announces when the server refuses a message.
        $log->onSending(new MessageEvent($email, new Envelope(new Address('a@pontiac.test'), [new Address('b@demo.test')]), 'smtp://example'));
        $log->onFailed(new FailedMessageEvent($email, new \RuntimeException('550 Mailbox unavailable')));

        $failed = $this->api('GET', '/api/platform/emails?status=failed')['items'];
        self::assertSame([['test', 'b@demo.test', 'failed', '550 Mailbox unavailable']], array_map(static fn (array $e) => [$e['kind'], $e['recipient'], $e['status'], $e['error']], $failed));
        self::assertSame(1, $this->api('GET', '/api/platform/dashboard')['failedEmailsLast7Days']);
    }

    public function testTheDashboardCountsConsultantsByStatus(): void
    {
        $this->createAccount('Finanzas Claras');
        $this->save($this->createAccount('Plata Sana')->setActive(false));
        $this->actAs($this->createSuperAdmin());

        self::assertSame(
            ['activeAccounts' => 1, 'suspendedAccounts' => 1, 'failedEmailsLast7Days' => 0, 'failedJobs' => 0],
            $this->api('GET', '/api/platform/dashboard'),
        );
    }

    public function testATestEmailNeedsAnAddressAndASuperAdmin(): void
    {
        $this->actAs($this->createSuperAdmin());
        $error = $this->api('POST', '/api/platform/emails/test', ['to' => 'nadie']);
        self::assertSame(422, $this->responseStatus());
        self::assertSame('to', $error['violations'][0]['field']);

        $this->actAs($this->createOwner($this->createAccount()));
        foreach ([['POST', '/api/platform/emails/test'], ['GET', '/api/platform/emails'], ['GET', '/api/platform/admins'], ['GET', '/api/platform/dashboard']] as [$method, $path]) {
            $this->api($method, $path, 'GET' === $method ? null : ['to' => 'yo@pontiac.test']);
            self::assertSame(403, $this->responseStatus(), $method.' '.$path);
        }
    }
}
