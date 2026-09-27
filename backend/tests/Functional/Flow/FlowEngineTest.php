<?php

declare(strict_types=1);

namespace App\Tests\Functional\Flow;

use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\EmailTemplate;
use App\Entity\Flow;
use App\Entity\FlowEvent;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\SessionStatus;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

/**
 * People move through a flow on their own: a page that feeds it brings them in, and booking, a session held, paying
 * or finishing moves them along the arrows. Entering a stage sends its email, with a way to stop them. The team moves
 * people by hand to any stage; every move is kept.
 */
final class FlowEngineTest extends ApiTestCase
{
    private Account $account;
    private User $owner;
    private Flow $flow;
    private Plan $free;
    /** @var array<string, string> stage ids by name */
    private array $stages = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->owner = $this->createOwner($this->account);
        $this->free = $this->createPlan($this->account, 'Diagnóstico gratuito');
        $this->flow = $this->createFlow($this->account);
        // "Sesión agendada" greets them.
        $template = new EmailTemplate($this->account, 'Agendado', 'Nos vemos pronto, {nombre}', "Tu sesión con {asesor} es el {fecha_sesion}.\n\nTus datos: {enlace_portal}");
        $this->save($template);
        foreach ($this->flow->getStages() as $stage) {
            $this->stages[$stage->getName()] = (string) $stage->getId();
            if ('Sesión agendada' === $stage->getName()) {
                $stage->change($stage->getName(), $stage->getKind(), $stage->getPosition(), $stage->getX(), $stage->getY(), $template, 3);
            }
        }
        $this->save($this->flow);
        $flowId = (string) $this->flow->getId();
        $planId = (string) $this->free->getId();
        $this->createPage($this->account, change: static function (array $content) use ($flowId, $planId): array {
            $content['settings']['flowId'] = $flowId;
            foreach ($content['sections'] as &$section) {
                if ('booking' === $section['type']) {
                    $section['enabled'] = true;
                    $section['fields']['planId'] = $planId;
                }
            }

            return $content;
        });
    }

    public function testAFormSentOnAPageThatFeedsTheFlowBringsThemIn(): void
    {
        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData());

        self::assertSame([['Diagnóstico', 'Nuevo']], $this->places('laura@demo.test'));
        self::assertSame([[null, 'Nuevo', 'added']], $this->moves('laura@demo.test'));
    }

    public function testBookingFromThePageMovesThemAndSendsTheStageEmail(): void
    {
        $slot = $this->freeSlots($this->account)[0];

        $this->client->request('POST', '/finanzas-claras/diagnostico/reservar', $this->formData(extra: ['section' => 'reserva', 'slot' => $slot->format(\DATE_ATOM)]));

        self::assertSame([['Diagnóstico', 'Sesión agendada']], $this->places('laura@demo.test'));
        $email = $this->emailWithSubject('Nos vemos pronto, Laura Gómez');
        self::assertStringContainsString('Tu sesión con Finanzas Claras es el ', (string) $email->getTextBody());
        self::assertStringContainsString('http://localhost:8080/finanzas-claras/portal', (string) $email->getHtmlBody());
        self::assertMatchesRegularExpression('#http://localhost:8080/finanzas-claras/correos/baja/[0-9a-f-]{36}/[0-9a-f]{32}#', (string) $email->getTextBody());
        self::assertStringStartsWith('<http://localhost:8080/finanzas-claras/correos/baja/', (string) $email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString());
        self::assertSame([[null, 'Nuevo', 'added'], ['Nuevo', 'Sesión agendada', 'session_booked']], $this->moves('laura@demo.test'));
        self::assertSame('Nos vemos pronto, Laura Gómez', $this->lastEvent('laura@demo.test')->getEmailSubject());
    }

    public function testEventsFollowTheArrowsOfTheirStageOnly(): void
    {
        $contact = $this->createContact($this->account);
        [$session] = $this->bookSession($contact, $this->free, new \DateTimeImmutable('-2 hours'));
        $this->actAs($this->owner);
        $this->api('POST', '/api/admin/flows/'.$this->flow->getId().'/people', ['contactId' => (string) $contact->getId()]);
        $this->move($contact, 'Sesión agendada');

        $this->api('POST', '/api/admin/sessions/'.$session->getId().'/done');
        self::assertSame([['Diagnóstico', 'Seguimiento']], $this->places('laura@demo.test'));

        // From Seguimiento, finishing is not an arrow: they stay.
        $enrollment = $session->getEnrollment()->getId();
        $this->api('POST', "/api/admin/enrollments/$enrollment/finish");
        self::assertSame([['Diagnóstico', 'Seguimiento']], $this->places('laura@demo.test'));

        $this->move($contact, 'Cliente');
        $this->api('POST', '/api/admin/sessions/'.$session->getId().'/reopen');
        $this->api('POST', '/api/admin/sessions/'.$session->getId().'/done');
        self::assertSame([['Diagnóstico', 'Cliente']], $this->places('laura@demo.test'), 'a session done is no arrow from Cliente');
        self::assertSame(['added', 'manual', 'session_done', 'manual'], array_column($this->moves('laura@demo.test'), 2));
    }

    public function testFinishingTheConsultancyEndsTheFlow(): void
    {
        $contact = $this->createContact($this->account);
        [$session] = $this->bookSession($contact, $this->free, new \DateTimeImmutable('-2 hours'));
        $this->save($session->close(SessionStatus::Done, new \DateTimeImmutable()), $session->getEnrollment());
        $this->save($session->getEnrollment());
        $this->actAs($this->owner);
        $this->api('POST', '/api/admin/sessions/'.$session->getId().'/reopen');
        $this->api('POST', '/api/admin/flows/'.$this->flow->getId().'/people', ['contactId' => (string) $contact->getId()]);
        $this->move($contact, 'Cliente');
        $this->api('POST', '/api/admin/sessions/'.$session->getId().'/done');

        $this->api('POST', '/api/admin/enrollments/'.$session->getEnrollment()->getId().'/finish');

        self::assertSame([['Diagnóstico', 'Finalizado']], $this->places('laura@demo.test'));
    }

    public function testThePersonStopsFlowEmailsFromTheLink(): void
    {
        $slot = $this->freeSlots($this->account)[0];
        $this->client->request('POST', '/finanzas-claras/diagnostico/reservar', $this->formData(extra: ['section' => 'reserva', 'slot' => $slot->format(\DATE_ATOM)]));
        preg_match('#/finanzas-claras/correos/baja/[0-9a-f-]{36}/[0-9a-f]{32}#', (string) $this->emailWithSubject('Nos vemos pronto, Laura Gómez')->getTextBody(), $link);

        $this->client->request('GET', $link[0]);
        self::assertSelectorExists('form button');
        self::assertNull($this->contact('laura@demo.test')->getFlowEmailsStoppedAt(), 'opening the link stops nothing');
        $this->client->request('POST', $link[0]);
        self::assertSelectorTextContains('[role="status"]', 'no te enviaremos más correos de seguimiento');
        self::assertNotNull($this->contact('laura@demo.test')->getFlowEmailsStoppedAt());

        $this->client->request('GET', substr($link[0], 0, -1).'0');
        self::assertSame(404, $this->responseStatus(), 'a forged signature');

        // Entering "Sesión agendada" again sends nothing now.
        $contact = $this->contact('laura@demo.test');
        $this->actAs($this->owner);
        $this->move($contact, 'Nuevo');
        $this->move($contact, 'Sesión agendada');
        self::assertSame([], $this->emails());
    }

    public function testAFlowTurnedOffMovesNobody(): void
    {
        $this->save($this->fresh($this->flow)->setActive(false));

        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData());

        self::assertSame([], $this->places('laura@demo.test'));
    }

    public function testTheBoardHistoryAndInicioShowWhoWaits(): void
    {
        $contact = $this->createContact($this->account);
        $carlos = $this->createContact($this->account, 'Carlos Ruiz', 'carlos@demo.test');
        $this->actAs($this->owner);
        foreach ([$contact, $carlos] as $person) {
            $this->api('POST', '/api/admin/flows/'.$this->flow->getId().'/people', ['contactId' => (string) $person->getId()]);
        }
        $this->move($contact, 'Sesión agendada');
        // Four days ago, past the stage's three-day alert.
        $this->em()->getConnection()->executeStatement('UPDATE contact_flow_state SET entered_at = ? WHERE contact_id = ?', [(new \DateTimeImmutable('-4 days'))->format('Y-m-d H:i:s'), $contact->getId()->toBinary()]);

        $board = $this->api('GET', '/api/admin/flows/'.$this->flow->getId().'/board');
        $columns = array_column($board['columns'], 'cards', 'name');
        self::assertSame(['Carlos Ruiz'], array_column($columns['Nuevo'], 'fullName'));
        self::assertSame([['Laura Gómez', 4, true]], array_map(static fn (array $c) => [$c['fullName'], $c['days'], $c['overdue']], $columns['Sesión agendada']));

        self::assertSame([['Laura Gómez', 'Diagnóstico', 'Sesión agendada', 4]], array_map(static fn (array $o) => [$o['fullName'], $o['flowName'], $o['stageName'], $o['days']], $this->api('GET', '/api/admin/dashboard')['overdue']));

        $history = $this->api('GET', '/api/admin/contacts/'.$contact->getId().'/history')['items'];
        self::assertSame(['manual', 'Nuevo', 'Sesión agendada', 'Andrés Asesor', 'Nos vemos pronto, Laura Gómez'], [$history[0]['reason'], $history[0]['fromStage'], $history[0]['toStage'], $history[0]['by'], $history[0]['subject']]);
        self::assertContains('flow', array_column($history, 'type'));

        $detail = $this->api('GET', '/api/admin/contacts/'.$contact->getId());
        self::assertSame(['Diagnóstico', 'Sesión agendada'], [$detail['flows'][0]['flowName'], $detail['flows'][0]['stageName']]);
        self::assertCount(5, $detail['flows'][0]['stages']);

        self::assertSame([], $this->api('DELETE', '/api/admin/flows/'.$this->flow->getId().'/people/'.$carlos->getId())['items']);
        self::assertSame('already_in_flow', $this->api('POST', '/api/admin/flows/'.$this->flow->getId().'/people', ['contactId' => (string) $contact->getId()])['error']);
    }

    public function testAnotherConsultantsPeopleAndStagesAreNotFound(): void
    {
        $theirs = $this->createAccount('Plata Sana');
        $pedro = $this->createContact($theirs, 'Pedro', 'pedro@demo.test');
        $theirFlow = $this->createFlow($theirs);
        $laura = $this->createContact($this->account);
        $this->actAs($this->owner);

        $this->api('POST', '/api/admin/flows/'.$this->flow->getId().'/people', ['contactId' => (string) $pedro->getId()]);
        self::assertSame(404, $this->responseStatus());
        $this->api('POST', '/api/admin/flows/'.$this->flow->getId().'/people', ['contactId' => (string) $laura->getId()]);
        $this->api('POST', '/api/admin/flows/'.$this->flow->getId().'/people/'.$laura->getId().'/move', ['stageId' => (string) $theirFlow->getStages()[1]->getId()]);
        self::assertSame(404, $this->responseStatus(), 'a stage of another flow');
    }

    private function move(Contact $contact, string $stage): void
    {
        $this->api('POST', '/api/admin/flows/'.$this->flow->getId().'/people/'.$contact->getId().'/move', ['stageId' => $this->stages[$stage]]);
        self::assertSame(200, $this->responseStatus());
    }

    /** @return list<array{0: string, 1: string}> [flow, stage] of the person */
    private function places(string $email): array
    {
        $contact = $this->contact($email);
        if (null === $contact) {
            return [];
        }

        return array_map(static fn ($s) => [$s->getFlow()->getName(), $s->getStage()->getName()], $this->em()->getRepository(\App\Entity\ContactFlowState::class)->findBy(['contact' => $contact]));
    }

    /** @return list<array{0: string|null, 1: string|null, 2: string}> [from, to, reason], oldest first */
    private function moves(string $email): array
    {
        return array_map(static fn (FlowEvent $e) => [$e->getFromStage(), $e->getToStage(), $e->getReason()], $this->em()->getRepository(FlowEvent::class)->findBy(['contact' => $this->contact($email)], ['createdAt' => 'ASC', 'id' => 'ASC']));
    }

    private function lastEvent(string $email): FlowEvent
    {
        return $this->em()->getRepository(FlowEvent::class)->findOneBy(['contact' => $this->contact($email)], ['createdAt' => 'DESC', 'id' => 'DESC']) ?? throw new \LogicException('No moves.');
    }

    private function contact(string $email): ?Contact
    {
        $this->asPlatform();
        $this->em()->clear();

        return $this->em()->getRepository(Contact::class)->findOneBy(['email' => $email]);
    }

    /** @return list<Email> queued or sent by the last request, flow emails only */
    private function emails(): array
    {
        return array_values(array_filter(array_map(static fn (MessageEvent $e) => $e->getMessage(), self::getMailerEvents()), static fn ($m) => $m instanceof Email && $m->getHeaders()->has('List-Unsubscribe')));
    }

    private function emailWithSubject(string $subject): Email
    {
        $found = array_values(array_filter($this->emails(), static fn (Email $e) => $subject === $e->getSubject()));
        self::assertCount(1, $found, 'one email "'.$subject.'"');

        return $found[0];
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function fresh(object $entity): object
    {
        $this->asPlatform();
        $this->em()->clear();

        return $this->em()->find($entity::class, $entity->getId()) ?? throw new \LogicException('Gone.');
    }
}
