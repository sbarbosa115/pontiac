<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Api\ApiResponse;
use App\Api\Output\PortalNoteOutput;
use App\Api\Presenter;
use App\Enum\AccountFeature;
use App\Repository\SessionNoteRepository;
use App\Security\RequiresFeature;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Notas: what the consultant shared about their sessions; private notes never reach the portal. */
#[Route('/api/portal/notes', name: 'api_portal_notes', methods: ['GET'])]
#[RequiresFeature(AccountFeature::Portal)]
final class NoteController extends PortalController
{
    #[ApiResponse(PortalNoteOutput::class, list: true, key: 'items')]
    public function __invoke(SessionNoteRepository $notes): JsonResponse
    {
        return $this->json(['items' => array_map(Presenter::portalNote(...), $notes->findSharedForContact($this->contact()))]);
    }
}
