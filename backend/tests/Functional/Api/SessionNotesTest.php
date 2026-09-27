<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\BookingSession;
use App\Entity\User;

/**
 * Notes of a session: private ones are the owner's alone; shared ones the assistant reads (and the client, in the
 * portal). A note is changed by its author or the owner.
 */
final class SessionNotesTest extends ApiTestCase
{
    private User $owner;
    private User $assistant;
    private BookingSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $account = $this->createAccount();
        $this->owner = $this->createOwner($account);
        $this->assistant = $this->createAssistant($account);
        [$this->session] = $this->bookSession($this->createContact($account), $this->createPlan($account), new \DateTimeImmutable('-1 hour'));
    }

    public function testPrivateNotesAreTheOwnersAlone(): void
    {
        $path = '/api/admin/sessions/'.$this->session->getId().'/notes';
        $this->actAs($this->owner);
        $private = $this->api('POST', $path, ['body' => 'Deuda de tarjeta: 12 millones al 28 %.', 'visibility' => 'private']);
        self::assertSame(201, $this->responseStatus());
        self::assertSame(['private', 'Andrés Asesor', true], [$private['visibility'], $private['author']['fullName'], $private['editable']]);
        $this->api('POST', $path, ['body' => 'Tarea: registrar gastos por una semana.', 'visibility' => 'shared']);
        self::assertCount(2, $this->api('GET', $path)['items']);

        $this->actAs($this->assistant);
        $notes = $this->api('GET', $path)['items'];
        self::assertSame([['Tarea: registrar gastos por una semana.', false]], array_map(static fn (array $n) => [$n['body'], $n['editable']], $notes));
        $this->api('POST', $path, ['body' => 'Algo', 'visibility' => 'private']);
        self::assertSame(403, $this->responseStatus());
        $this->api('PUT', '/api/admin/session-notes/'.$private['id'], ['body' => 'Cambiada', 'visibility' => 'shared']);
        self::assertSame(404, $this->responseStatus(), 'a private note does not exist for them');
        $this->api('PUT', '/api/admin/session-notes/'.$notes[0]['id'], ['body' => 'Cambiada', 'visibility' => 'shared']);
        self::assertSame(403, $this->responseStatus(), 'the owner\'s note');

        $mine = $this->api('POST', $path, ['body' => 'Envié la plantilla.', 'visibility' => 'shared']);
        self::assertTrue($mine['editable']);
        self::assertSame('Envié la plantilla por correo.', $this->api('PUT', '/api/admin/session-notes/'.$mine['id'], ['body' => ' Envié la plantilla por correo. ', 'visibility' => 'shared'])['body']);

        $this->actAs($this->owner);
        self::assertTrue($this->api('GET', $path)['items'][2]['editable'], 'the owner changes any note');
    }

    public function testMistakesComeBackInSpanish(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es');
        $this->actAs($this->owner);

        $error = $this->api('POST', '/api/admin/sessions/'.$this->session->getId().'/notes', ['body' => ' ', 'visibility' => 'todos']);
        self::assertSame(['body' => 'Escribe la nota.', 'visibility' => 'Elige quién puede leerla.'], array_column($error['violations'], 'message', 'field'));
    }

    public function testAnotherConsultantsSessionsAndNotesAreNotFound(): void
    {
        $other = $this->createAccount('Plata Sana');
        $theirOwner = $this->createOwner($other, 'paola@plata.test');
        [$theirs] = $this->bookSession($this->createContact($other, 'Pedro', 'pedro@demo.test'), $this->createPlan($other), new \DateTimeImmutable('-1 hour'));
        $this->actAs($theirOwner);
        $note = $this->api('POST', '/api/admin/sessions/'.$theirs->getId().'/notes', ['body' => 'Suya', 'visibility' => 'shared']);

        $this->actAs($this->owner);
        $this->api('GET', '/api/admin/sessions/'.$theirs->getId().'/notes');
        self::assertSame(404, $this->responseStatus());
        $this->api('POST', '/api/admin/sessions/'.$theirs->getId().'/notes', ['body' => 'x', 'visibility' => 'shared']);
        self::assertSame(404, $this->responseStatus());
        $this->api('PUT', '/api/admin/session-notes/'.$note['id'], ['body' => 'x', 'visibility' => 'shared']);
        self::assertSame(404, $this->responseStatus());
    }
}
