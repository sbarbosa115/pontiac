<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\Input\SessionNoteInput;
use App\Api\InputMapper;
use App\Api\Output\SessionNoteOutput;
use App\Api\Presenter;
use App\Entity\SessionNote;
use App\Entity\User;
use App\Enum\AccountFeature;
use App\Enum\NoteVisibility;
use App\Repository\BookingSessionRepository;
use App\Repository\SessionNoteRepository;
use App\Security\RequiresFeature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * A session's notes. Private ones are the owner's alone: an assistant neither reads nor writes them (a private
 * note's id answers 404 to them). A note is changed by its author or the owner, never deleted.
 */
#[Route('/api/admin', name: 'api_admin_session_note_')]
#[RequiresFeature(AccountFeature::Booking)]
final class SessionNoteController extends ApiController
{
    public function __construct(
        private readonly SessionNoteRepository $notes,
        private readonly InputMapper $input,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/sessions/{id}/notes', name: 'list', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[ApiResponse(SessionNoteOutput::class, list: true, key: 'items')]
    public function list(string $id, BookingSessionRepository $sessions): JsonResponse
    {
        $session = $this->found($sessions->findOneById($id));

        return $this->json(['items' => array_map($this->present(...), $this->notes->findForSession($session, !$this->isOwner()))]);
    }

    #[Route('/sessions/{id}/notes', name: 'create', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(SessionNoteOutput::class, status: 201)]
    public function create(string $id, Request $request, BookingSessionRepository $sessions): JsonResponse
    {
        $session = $this->found($sessions->findOneById($id));
        [$body, $visibility] = $this->read($request);
        $note = new SessionNote($session, $this->appUser(), $body, $visibility);
        $this->em->persist($note);
        $this->em->flush();

        return $this->json($this->present($note), 201);
    }

    #[Route('/session-notes/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    #[ApiResponse(SessionNoteOutput::class)]
    public function update(string $id, Request $request): JsonResponse
    {
        $note = $this->found($this->notes->findOneById($id));
        if (NoteVisibility::Private === $note->getVisibility() && !$this->isOwner()) {
            throw ApiException::notFound();
        }
        if (!$this->mayChange($note)) {
            throw ApiException::forbidden('forbidden', 'Only its author or the owner changes a note.');
        }
        [$body, $visibility] = $this->read($request);
        $note->change($body, $visibility);
        $this->em->flush();

        return $this->json($this->present($note));
    }

    /**
     * @return array{0: string, 1: NoteVisibility}
     */
    private function read(Request $request): array
    {
        $data = $this->input->map($this->input->json($request), SessionNoteInput::class);
        $visibility = NoteVisibility::from((string) $data->visibility);
        if (NoteVisibility::Private === $visibility && !$this->isOwner()) {
            throw ApiException::forbidden('forbidden', 'Only the owner writes private notes.');
        }

        return [trim((string) $data->body), $visibility];
    }

    private function present(SessionNote $note): SessionNoteOutput
    {
        return Presenter::sessionNote($note, $this->mayChange($note));
    }

    private function mayChange(SessionNote $note): bool
    {
        return $this->isOwner() || $note->getAuthor()->getId()->equals($this->appUser()->getId());
    }

    private function isOwner(): bool
    {
        return $this->isGranted(User::ROLE_OWNER);
    }
}
