<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Api\ApiResponse;
use App\Api\Output\ClientFileOutput;
use App\Api\Presenter;
use App\Entity\ClientFile;
use App\Enum\AccountFeature;
use App\Portal\ClientFiles;
use App\Repository\ClientFileRepository;
use App\Security\RequiresFeature;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** Archivos: what the consultant shared and what they uploaded themselves. Internal files do not exist here. */
#[Route('/api/portal/files', name: 'api_portal_file_')]
#[RequiresFeature(AccountFeature::Portal)]
final class FileController extends PortalController
{
    public function __construct(private readonly ClientFileRepository $files)
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(ClientFileOutput::class, list: true, key: 'items')]
    public function list(): JsonResponse
    {
        return $this->json(['items' => array_map(Presenter::clientFile(...), $this->files->findForContact($this->contact(), forClient: true))]);
    }

    /** Multipart `file`: theirs, so the consultant sees it and so do they. */
    #[Route('', name: 'upload', methods: ['POST'])]
    #[ApiResponse(ClientFileOutput::class, status: 201)]
    public function upload(Request $request, ClientFiles $store): JsonResponse
    {
        $file = $store->upload($this->account(), $this->contact(), $request->files->get('file'), $this->appUser(), true);

        return $this->json(Presenter::clientFile($file), 201);
    }

    #[Route('/{id}/download', name: 'download', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function download(string $id, ClientFiles $store): Response
    {
        return $store->download($this->load($id));
    }

    private function load(string $id): ClientFile
    {
        $file = $this->found($this->files->findOneById($id));
        $this->own($file->getContact());
        if (!$file->isActive() || !$file->isShared()) {
            $this->found(null);
        }

        return $file;
    }
}
