<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Input\MediaUpdateInput;
use App\Api\InputMapper;
use App\Api\Output\MediaAssetOutput;
use App\Api\Presenter;
use App\Entity\MediaAsset;
use App\Media\MediaUploader;
use App\Repository\MediaAssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** Ajustes › Medios: the images the consultant's pages use. */
#[Route('/api/admin/media', name: 'api_admin_media_')]
final class MediaController extends ApiController
{
    public function __construct(
        private readonly MediaAssetRepository $media,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** ?q= searches file name and alt text; ?includeInactive=1 shows disabled ones too. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(MediaAssetOutput::class, page: true)]
    public function list(Request $request): JsonResponse
    {
        $page = $this->media->search($request->query->getString('q'), $request->query->getBoolean('includeInactive'), $this->pagination($request));

        return $this->page($page, Presenter::mediaAsset(...));
    }

    /** multipart/form-data: "file", and optionally "altText". */
    #[Route('', name: 'upload', methods: ['POST'])]
    #[ApiResponse(MediaAssetOutput::class, status: 201)]
    public function upload(Request $request, MediaUploader $uploader): JsonResponse
    {
        $asset = $uploader->upload($this->account(), $request->files->get('file'));
        $alt = $request->request->getString('altText');
        if ('' !== $alt) {
            $asset->setAltText($alt);
            $this->em->flush();
        }

        return $this->json(Presenter::mediaAsset($asset), 201);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[ApiResponse(MediaAssetOutput::class)]
    public function show(string $id): JsonResponse
    {
        return $this->json(Presenter::mediaAsset($this->load($id)));
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    #[ApiResponse(MediaAssetOutput::class)]
    public function update(string $id, Request $request, InputMapper $input): JsonResponse
    {
        $data = $input->map($input->json($request), MediaUpdateInput::class);
        $asset = $this->load($id)->setAltText((string) $data->altText);
        $this->em->flush();

        return $this->json(Presenter::mediaAsset($asset));
    }

    /** No longer offered for new content; pages that show it keep showing it. */
    #[Route('/{id}', name: 'disable', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[ApiResponse(MediaAssetOutput::class)]
    public function disable(string $id): JsonResponse
    {
        $asset = $this->load($id)->setActive(false);
        $this->em->flush();

        return $this->json(Presenter::mediaAsset($asset));
    }

    #[Route('/{id}/enable', name: 'enable', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(MediaAssetOutput::class)]
    public function enable(string $id): JsonResponse
    {
        $asset = $this->load($id)->setActive(true);
        $this->em->flush();

        return $this->json(Presenter::mediaAsset($asset));
    }

    private function load(string $id): MediaAsset
    {
        return $this->found($this->media->findOneById($id));
    }
}
