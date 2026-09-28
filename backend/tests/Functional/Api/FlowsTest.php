<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Account;
use App\Entity\EmailTemplate;
use App\Entity\User;
use App\Enum\AccountFeature;

/**
 * Flujos: a new flow starts from the ready example; the editor saves the whole canvas and says where each problem is;
 * a stage with people cannot be removed. Every consultant's flows are their own.
 */
final class FlowsTest extends ApiTestCase
{
    private Account $account;
    private User $assistant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->createOwner($this->account);
        $this->assistant = $this->createAssistant($this->account);
    }

    public function testANewFlowIsTheReadyExample(): void
    {
        $this->actAs($this->assistant);

        $flow = $this->api('POST', '/api/admin/flows', ['name' => ' Diagnóstico ']);

        self::assertSame(201, $this->responseStatus());
        self::assertSame('Diagnóstico', $flow['name']);
        self::assertSame(['Nuevo', 'Sesión agendada', 'Seguimiento', 'Cliente', 'Finalizado'], array_column($flow['stages'], 'name'));
        self::assertSame(['start', 'step', 'step', 'step', 'end'], array_column($flow['stages'], 'kind'));
        $names = array_column($flow['stages'], 'name', 'id');
        self::assertContains(['Nuevo', 'Sesión agendada', 'session_booked'], array_map(static fn (array $t) => [$names[$t['from']], $names[$t['to']], $t['trigger']], $flow['transitions']));
        self::assertSame([['Diagnóstico', 5, 0, true]], array_map(static fn (array $f) => [$f['name'], $f['stages'], $f['people'], $f['active']], $this->api('GET', '/api/admin/flows')['items']));
    }

    public function testTheEditorSavesTheWholeCanvas(): void
    {
        $this->actAs($this->assistant);
        $flow = $this->api('POST', '/api/admin/flows', ['name' => 'Diagnóstico']);
        $template = $this->api('POST', '/api/admin/email-templates', ['name' => 'Bienvenida', 'subject' => 'Hola {nombre}', 'body' => 'Gracias por escribir a {asesor}.']);

        $stages = $flow['stages'];
        $stages[1]['name'] = 'Agendado';
        $stages[1]['emailTemplateId'] = $template['id'];
        array_splice($stages, 4, 1);
        $stages[] = ['id' => 'new-1', 'name' => 'Seguimiento 2', 'kind' => 'step', 'x' => 900, 'y' => 300, 'emailTemplateId' => null, 'alertDays' => 14];
        $transitions = [
            ['from' => $stages[0]['id'], 'to' => $stages[1]['id'], 'trigger' => 'session_booked'],
            ['from' => $stages[2]['id'], 'to' => 'new-1', 'trigger' => 'manual'],
        ];

        $saved = $this->api('PUT', '/api/admin/flows/'.$flow['id'], ['name' => 'Diagnóstico gratuito', 'stages' => $stages, 'transitions' => $transitions]);

        self::assertSame(200, $this->responseStatus());
        self::assertSame(['Nuevo', 'Agendado', 'Seguimiento', 'Cliente', 'Seguimiento 2'], array_column($saved['stages'], 'name'));
        self::assertSame($template['id'], $saved['stages'][1]['emailTemplateId']);
        self::assertSame(14, $saved['stages'][4]['alertDays']);
        $names = array_column($saved['stages'], 'name', 'id');
        self::assertSame([['Nuevo', 'Agendado', 'session_booked'], ['Seguimiento', 'Seguimiento 2', 'manual']], array_map(static fn (array $t) => [$names[$t['from']], $names[$t['to']], $t['trigger']], $saved['transitions']));
    }

    public function testEveryProblemOfTheCanvasComesBackWhereItIs(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es');
        $this->actAs($this->assistant);
        $flow = $this->api('POST', '/api/admin/flows', ['name' => 'Diagnóstico']);
        $stages = $flow['stages'];
        $stages[1]['kind'] = 'start';
        $stages[2]['name'] = '';
        $stages[3]['alertDays'] = 0;
        $from = $stages[0]['id'];

        $error = $this->api('PUT', '/api/admin/flows/'.$flow['id'], ['name' => 'Diagnóstico', 'stages' => $stages, 'transitions' => [
            ['from' => $from, 'to' => $stages[1]['id'], 'trigger' => 'session_booked'],
            ['from' => $from, 'to' => $stages[2]['id'], 'trigger' => 'session_booked'],
            ['from' => $from, 'to' => $from, 'trigger' => 'manual'],
            ['from' => $from, 'to' => $stages[3]['id'], 'trigger' => 'teleport'],
        ]]);

        self::assertSame(422, $this->responseStatus());
        self::assertSame([
            'stages[2].name' => 'Ponle nombre a la etapa (máximo 80 caracteres).',
            'stages[3].alertDays' => 'Avisa después de 1 a 365 días, o déjalo vacío.',
            'stages' => 'Un flujo tiene exactamente una etapa de inicio.',
            'transitions[1].trigger' => 'Una etapa tiene una sola flecha por evento: el evento no sabría cuál seguir.',
            'transitions[2]' => 'Una flecha va de una etapa a otra.',
            'transitions[3].trigger' => 'Elige qué mueve a las personas por esta flecha.',
        ], array_column($error['violations'], 'message', 'field'));
    }

    public function testAStageWithPeopleIsNotRemoved(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es');
        $contact = $this->createContact($this->account);
        $this->actAs($this->assistant);
        $flow = $this->api('POST', '/api/admin/flows', ['name' => 'Diagnóstico']);
        $this->api('POST', '/api/admin/flows/'.$flow['id'].'/people', ['contactId' => (string) $contact->getId()]);

        $error = $this->api('PUT', '/api/admin/flows/'.$flow['id'], ['name' => 'Diagnóstico', 'stages' => \array_slice($flow['stages'], 1), 'transitions' => []]);

        self::assertContains('Mueve a las personas de «Nuevo» a otra etapa antes de quitarla.', array_column($error['violations'], 'message'));
    }

    public function testTheEmailsFlowsSend(): void
    {
        $this->actAs($this->assistant);

        $error = $this->api('POST', '/api/admin/email-templates', ['name' => ' ', 'subject' => '', 'body' => '']);
        self::assertSame(422, $this->responseStatus());
        self::assertSame(['name', 'subject', 'body'], array_column($error['violations'], 'field'));

        $template = $this->api('POST', '/api/admin/email-templates', ['name' => ' Bienvenida ', 'subject' => 'Hola {nombre}', 'body' => 'Reserva aquí: {enlace_reserva}']);
        self::assertSame(201, $this->responseStatus());
        self::assertSame(['Bienvenida', 'Hola {nombre}', true], [$template['name'], $template['subject'], $template['active']]);

        $changed = $this->api('PUT', '/api/admin/email-templates/'.$template['id'], ['name' => 'Bienvenida', 'subject' => 'Te damos la bienvenida', 'body' => 'Hola.']);
        self::assertSame('Te damos la bienvenida', $changed['subject']);
        self::assertFalse($this->api('DELETE', '/api/admin/email-templates/'.$template['id'])['active']);
        self::assertSame([], $this->api('GET', '/api/admin/email-templates')['items'], 'disabled ones are hidden unless asked');
        self::assertCount(1, $this->api('GET', '/api/admin/email-templates?includeInactive=1')['items']);

        $theirs = new EmailTemplate($this->createAccount('Plata Sana'), 'Suyo', 'Asunto', 'Cuerpo');
        $this->save($theirs);
        $this->actAs($this->assistant);
        $this->api('PUT', '/api/admin/email-templates/'.$theirs->getId(), ['name' => 'Mío', 'subject' => 'Asunto', 'body' => 'Cuerpo']);
        self::assertSame(404, $this->responseStatus());
        $this->api('DELETE', '/api/admin/email-templates/'.$theirs->getId());
        self::assertSame(404, $this->responseStatus());
    }

    public function testAPageFeedsOnlyOneOfTheConsultantsActiveFlows(): void
    {
        $page = $this->createPage($this->account, published: false);
        $mine = $this->createFlow($this->account);
        $theirs = $this->createFlow($this->createAccount('Plata Sana'));
        $this->actAs($this->assistant);
        $draft = $this->api('GET', '/api/admin/pages/'.$page->getId())['draft'];

        foreach ([(string) $theirs->getId(), '0190a1b2-0000-7000-8000-000000000000'] as $flowId) {
            $draft['settings']['flowId'] = $flowId;
            $error = $this->api('PATCH', '/api/admin/pages/'.$page->getId(), ['draft' => $draft]);
            self::assertSame(422, $this->responseStatus());
            self::assertSame('settings.flowId', $error['violations'][0]['field']);
        }

        $draft['settings']['flowId'] = (string) $mine->getId();
        self::assertSame((string) $mine->getId(), $this->api('PATCH', '/api/admin/pages/'.$page->getId(), ['draft' => $draft])['draft']['settings']['flowId']);
    }

    public function testAnotherConsultantsFlowsAndAFeatureTurnedOff(): void
    {
        $theirs = $this->createFlow($this->createAccount('Plata Sana'));
        $this->actAs($this->assistant);
        foreach ([['GET', ''], ['GET', '/board'], ['DELETE', ''], ['POST', '/enable']] as [$method, $path]) {
            $this->api($method, '/api/admin/flows/'.$theirs->getId().$path);
            self::assertSame(404, $this->responseStatus(), $method.$path);
        }

        $this->save($this->fresh($this->account)->setFeatures([AccountFeature::Booking]));
        $this->actAs($this->assistant);
        self::assertSame('feature_disabled', $this->api('GET', '/api/admin/flows')['error']);
        self::assertSame('feature_disabled', $this->api('GET', '/api/admin/email-templates')['error']);
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
