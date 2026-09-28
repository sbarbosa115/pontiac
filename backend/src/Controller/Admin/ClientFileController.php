<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Input\ClientFileUpdateInput;
use App\Api\InputMapper;
use App\Api\Output\ClientFileOutput;
use App\Api\Presenter;
use App\Entity\ClientFile;
use App\Portal\ClientFiles;
use App\Repository\ClientFileRepository;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * A contact's Archivos: the team uploads files (shared with the client or internal), downloads them and the client's
 * own, shares or unshares them, turns them off.
 */
#[Route('/api/admin', name: 'api_admin_client_file_')]
final class ClientFileController extends ApiController
{
    public function __construct(
        private readonly ClientFileRepository $files,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Every file of theirs, turned-off ones too, newest first. */
    #[Route('/contacts/{id}/files', name: 'list', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[ApiResponse(ClientFileOutput::class, list: true, key: 'items')]
    public function list(string $id, ContactRepository $contacts): JsonResponse
    {
        $contact = $this->found($contacts->findOneById($id));

        return $this->json(['items' => array_map(Presenter::clientFile(...), $this->files->findForContact($contact))]);
    }

    /** Multipart: `file`, and `shared` ("1": the client sees it). */
    #[Route('/contacts/{id}/files', name: 'upload', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(ClientFileOutput::class, status: 201)]
    public function upload(string $id, Request $request, ContactRepository $contacts, ClientFiles $store): JsonResponse
    {
        $contact = $this->found($contacts->findOneById($id));
        $file = $store->upload($this->account(), $contact, $request->files->get('file'), $this->appUser(), $request->request->getBoolean('shared'));

        return $this->json(Presenter::clientFile($file), 201);
    }

    #[Route('/client-files/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    #[ApiResponse(ClientFileOutput::class)]
    public function update(string $id, Request $request, InputMapper $input): JsonResponse
    {
        $data = $input->map($input->json($request), ClientFileUpdateInput::class);
        $file = $this->load($id)->share((bool) $data->shared);
        $this->em->flush();

        return $this->json(Presenter::clientFile($file));
    }

    #[Route('/client-files/{id}', name: 'disable', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[ApiResponse(ClientFileOutput::class)]
    public function disable(string $id): JsonResponse
    {
        $file = $this->load($id)->setActive(false);
        $this->em->flush();

        return $this->json(Presenter::clientFile($file));
    }

    #[Route('/client-files/{id}/enable', name: 'enable', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(ClientFileOutput::class)]
    public function enable(string $id): JsonResponse
    {
        $file = $this->load($id)->setActive(true);
        $this->em->flush();

        return $this->json(Presenter::clientFile($file));
    }

    #[Route('/client-files/{id}/download', name: 'download', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function download(string $id, ClientFiles $store): Response
    {
        return $store->download($this->load($id));
    }

    private function load(string $id): ClientFile
    {
        return $this->found($this->files->findOneById($id));
    }
}
