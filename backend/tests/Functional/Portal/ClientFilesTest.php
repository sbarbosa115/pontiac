<?php

declare(strict_types=1);

namespace App\Tests\Functional\Portal;

use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\User;
use App\Tests\Functional\Api\ApiTestCase;

/**
 * Archivos: the team uploads a person's files, shared with them or internal; the client downloads what is shared and
 * uploads their own (the team sees those). Types come from the content; limits are the consultant's.
 */
final class ClientFilesTest extends ApiTestCase
{
    private Account $account;
    private User $assistant;
    private Contact $laura;
    private User $login;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->createOwner($this->account);
        $this->assistant = $this->createAssistant($this->account);
        $this->laura = $this->createContact($this->account);
        $this->login = $this->createClientFor($this->laura);
    }

    public function testTheClientSeesWhatIsSharedAndTheTeamSeesEverything(): void
    {
        $this->actAs($this->assistant);
        $shared = $this->upload('/api/admin/contacts/'.$this->laura->getId().'/files', ['shared' => '1'], ['file' => $this->pdfFile('presupuesto.pdf')]);
        self::assertSame(201, $this->responseStatus());
        self::assertSame(['presupuesto.pdf', 'application/pdf', true, false, 'Sofía Asistente'], [$shared['name'], $shared['contentType'], $shared['shared'], $shared['byClient'], $shared['uploadedBy']]);
        $internal = $this->upload('/api/admin/contacts/'.$this->laura->getId().'/files', [], ['file' => $this->pdfFile('analisis-interno.pdf')]);
        self::assertFalse($internal['shared']);

        $this->actAs($this->login);
        self::assertSame(['presupuesto.pdf'], array_column($this->api('GET', '/api/portal/files')['items'], 'name'));
        $this->client->request('GET', '/api/portal/files/'.$shared['id'].'/download');
        self::assertSame(200, $this->responseStatus());
        self::assertStringStartsWith('%PDF-', (string) $this->client->getInternalResponse()->getContent());
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        $this->client->request('GET', '/api/portal/files/'.$internal['id'].'/download');
        self::assertSame(404, $this->responseStatus(), 'an internal file does not exist for them');

        $mine = $this->upload('/api/portal/files', [], ['file' => $this->pdfFile('extracto-tarjeta.pdf')]);
        self::assertSame([true, true, 'Laura Gómez'], [$mine['shared'], $mine['byClient'], $mine['uploadedBy']]);

        $this->actAs($this->assistant);
        $all = $this->api('GET', '/api/admin/contacts/'.$this->laura->getId().'/files')['items'];
        self::assertSame(['extracto-tarjeta.pdf', 'analisis-interno.pdf', 'presupuesto.pdf'], array_column($all, 'name'));
        self::assertTrue($this->api('PATCH', '/api/admin/client-files/'.$mine['id'], ['shared' => false])['shared'], 'what the client uploaded stays theirs');
        self::assertFalse($this->api('DELETE', '/api/admin/client-files/'.$shared['id'])['active']);

        $this->actAs($this->login);
        self::assertSame(['extracto-tarjeta.pdf'], array_column($this->api('GET', '/api/portal/files')['items'], 'name'), 'a file turned off leaves the portal');
    }

    public function testOnlyTheAllowedTypesWithinTheLimits(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es');
        $script = (string) tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($script, "#!/bin/sh\necho hola\n");
        $this->actAs($this->login);

        $error = $this->upload('/api/portal/files', [], ['file' => new \Symfony\Component\HttpFoundation\File\UploadedFile($script, 'factura.pdf', 'application/pdf', null, true)]);
        self::assertSame(422, $this->responseStatus(), 'the name says PDF, the content does not');
        self::assertStringStartsWith('Sube un PDF', $error['violations'][0]['message']);

        $this->save($this->fresh($this->account)->setLimits(10, 3, 0, 5));
        $this->actAs($this->login);
        self::assertSame('storage_limit_reached', $this->upload('/api/portal/files', [], ['file' => $this->pdfFile()])['error']);
    }

    public function testAnotherPersonsOrConsultantsFilesAreNotFound(): void
    {
        $carlos = $this->createContact($this->account, 'Carlos', 'carlos@demo.test');
        $pedro = $this->createContact($this->createAccount('Plata Sana'), 'Pedro', 'pedro@demo.test');
        $this->actAs($this->assistant);
        $his = $this->upload('/api/admin/contacts/'.$carlos->getId().'/files', ['shared' => '1'], ['file' => $this->pdfFile()]);
        $this->api('GET', '/api/admin/contacts/'.$pedro->getId().'/files');
        self::assertSame(404, $this->responseStatus());

        $this->actAs($this->login);
        $this->client->request('GET', '/api/portal/files/'.$his['id'].'/download');
        self::assertSame(404, $this->responseStatus());
        $this->client->request('GET', '/api/admin/client-files/'.$his['id'].'/download');
        self::assertSame(403, $this->responseStatus(), 'a client never reaches the team\'s API');
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
