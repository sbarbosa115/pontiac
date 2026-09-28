<?php

declare(strict_types=1);

namespace App\Tests\Functional\Portal;

use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\User;
use App\Enum\AccountFeature;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

/**
 * Who can sign in to a consultant's portal: invited by hand from the contact's page, or on paying a first plan; the
 * access can be taken away and given back; erasing the person's data closes it.
 */
final class PortalAccessTest extends ApiTestCase
{
    private Account $account;
    private User $assistant;
    private Contact $laura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->createOwner($this->account);
        $this->assistant = $this->createAssistant($this->account);
        $this->laura = $this->createContact($this->account);
    }

    public function testTheTeamInvitesAPersonWhoThenSignsInAtThePortal(): void
    {
        $this->actAs($this->assistant);
        self::assertSame('none', $this->api('GET', $this->contactPath())['portal']['status']);

        $detail = $this->api('POST', $this->contactPath().'/portal/invitation');
        self::assertSame('invited', $detail['portal']['status']);
        $email = $this->sentTo('laura@demo.test');
        self::assertSame('Finanzas Claras te dio acceso a tu portal de cliente', $email->getSubject());
        self::assertSame(1, preg_match('#/invitacion\?token=([\w-]+)#', (string) $email->getTextBody(), $link));

        $this->signOut();
        $this->api('POST', '/api/invitations/accept', ['token' => $link[1], 'password' => 'una-clave-larga-123']);
        self::assertSame(204, $this->responseStatus());
        $token = $this->api('POST', '/api/portal-login', ['account' => 'finanzas-claras', 'email' => 'laura@demo.test', 'password' => 'una-clave-larga-123']);
        self::assertArrayHasKey('token', $token);

        $this->actAs($this->assistant);
        self::assertSame('active', $this->api('GET', $this->contactPath())['portal']['status']);
        self::assertSame('already_has_access', $this->api('POST', $this->contactPath().'/portal/invitation')['error']);
    }

    public function testTakingTheAccessAwayClosesThePortal(): void
    {
        $this->createClientFor($this->laura);
        $other = $this->createContact($this->account, 'Carlos', 'carlos@demo.test');
        $this->actAs($this->assistant);

        self::assertSame('disabled', $this->api('POST', $this->contactPath().'/portal/disable')['portal']['status']);
        $this->signOut();
        $this->api('POST', '/api/portal-login', ['account' => 'finanzas-claras', 'email' => 'laura@demo.test', 'password' => self::PASSWORD]);
        self::assertSame(401, $this->responseStatus());

        $this->actAs($this->assistant);
        self::assertSame('active', $this->api('POST', $this->contactPath().'/portal/enable')['portal']['status']);
        $this->api('POST', '/api/admin/contacts/'.$other->getId().'/portal/disable');
        self::assertSame(409, $this->responseStatus(), 'never invited');
    }

    public function testErasingThePersonsDataClosesTheirPortal(): void
    {
        $this->createClientFor($this->laura);
        $this->actAs($this->createOwner($this->account, 'otro@demo.test'));

        self::assertSame('disabled', $this->api('POST', $this->contactPath().'/anonymize')['portal']['status']);
    }

    public function testPayingAFirstPlanSendsTheInvitation(): void
    {
        $paid = $this->createPlan($this->account, 'Plan A', '250000.00', 2, 60);
        $owner = $this->createOwner($this->account, 'duena@demo.test');
        $this->actAs($owner);
        $enrollment = $this->api('POST', $this->contactPath().'/enrollments', ['planId' => (string) $paid->getId()])['enrollments'][0]['id'];

        $this->api('POST', "/api/admin/enrollments/$enrollment/payments", ['method' => 'cash']);

        self::assertSame('Finanzas Claras te dio acceso a tu portal de cliente', $this->sentTo('laura@demo.test')->getSubject());
        self::assertSame('invited', $this->api('GET', $this->contactPath())['portal']['status']);

        // A second plan paid: no second invitation.
        $second = $this->api('POST', $this->contactPath().'/enrollments', ['planId' => (string) $paid->getId()])['enrollments'][0]['id'];
        $this->api('POST', "/api/admin/enrollments/$second/payments", ['method' => 'cash']);
        self::assertSame([], $this->sent());
    }

    public function testAFreePlanOrAPortalTurnedOffInvitesNobody(): void
    {
        $this->save($this->account->setFeatures([AccountFeature::Booking, AccountFeature::Payments]));
        $this->actAs($this->assistant);

        self::assertSame('feature_disabled', $this->api('POST', $this->contactPath().'/portal/invitation')['error']);
    }

    public function testAnotherConsultantsPeopleAreNotFound(): void
    {
        $theirs = $this->createContact($this->createAccount('Plata Sana'), 'Pedro', 'pedro@demo.test');
        $this->actAs($this->assistant);

        foreach (['invitation', 'disable', 'enable'] as $action) {
            $this->api('POST', '/api/admin/contacts/'.$theirs->getId().'/portal/'.$action);
            self::assertSame(404, $this->responseStatus(), $action);
        }
    }

    private function contactPath(): string
    {
        return '/api/admin/contacts/'.$this->laura->getId();
    }

    /** @return list<Email> sent at once (invitations are not queued) by the last request */
    private function sent(): array
    {
        return array_values(array_filter(array_map(
            static fn (MessageEvent $e) => $e->isQueued() ? null : $e->getMessage(),
            self::getMailerEvents(),
        ), static fn ($m) => $m instanceof Email && str_contains((string) $m->getSubject(), 'portal')));
    }

    private function sentTo(string $address): Email
    {
        $emails = array_values(array_filter($this->sent(), static fn (Email $e) => $address === $e->getTo()[0]->getAddress()));
        self::assertCount(1, $emails);

        return $emails[0];
    }
}
