<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\Input\InviteTeamMemberInput;
use App\Api\InputMapper;
use App\Api\Output\TeamMemberOutput;
use App\Api\Presenter;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\InvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ajustes › Equipo: the consultant (owner) and the assistants they invited. Everyone on the team sees it; only the
 * owner invites, disables and enables.
 */
#[Route('/api/admin/team', name: 'api_admin_team_')]
final class TeamController extends ApiController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly InvitationService $invitations,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** ?q= searches name and email. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(TeamMemberOutput::class, page: true)]
    public function list(Request $request): JsonResponse
    {
        $page = $this->users->searchTeam($this->account(), $request->query->getString('q'), $this->pagination($request));

        return $this->page($page, Presenter::teamMember(...));
    }

    /** Invites an assistant: they get an email to set their password. */
    #[Route('', name: 'invite', methods: ['POST'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(TeamMemberOutput::class, status: 201)]
    public function invite(Request $request, InputMapper $input): JsonResponse
    {
        $data = $input->map($input->json($request), InviteTeamMemberInput::class);
        $this->assertRoomForOneMore();
        $user = $this->invitations->inviteAssistant($this->account(), (string) $data->email, trim((string) $data->fullName));

        return $this->json(Presenter::teamMember($user), 201);
    }

    /** A new invitation link, for someone who has not set their password yet. */
    #[Route('/{id}/resend-invitation', name: 'resend', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(TeamMemberOutput::class)]
    public function resend(string $id): JsonResponse
    {
        $user = $this->invitations->resend($this->member($id));

        return $this->json(Presenter::teamMember($user));
    }

    /** Disables an assistant: they are signed out at their next request. Nothing is deleted. */
    #[Route('/{id}', name: 'disable', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(TeamMemberOutput::class)]
    public function disable(string $id): JsonResponse
    {
        $user = $this->member($id);
        if ($user->hasRole(User::ROLE_OWNER)) {
            throw ApiException::conflict('owner_cannot_be_disabled', 'The consultant cannot be disabled from their own team.');
        }
        $user->setActive(false);
        $this->em->flush();

        return $this->json(Presenter::teamMember($user));
    }

    #[Route('/{id}/enable', name: 'enable', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(TeamMemberOutput::class)]
    public function enable(string $id): JsonResponse
    {
        $user = $this->member($id);
        if (!$user->isActive()) {
            $this->assertRoomForOneMore();
        }
        $user->setActive(true);
        $this->em->flush();

        return $this->json(Presenter::teamMember($user));
    }

    /**
     * The consultant's plan allows so many active assistants (Asesores › Límites y funciones); a disabled one does not
     * count, so disabling someone makes room.
     */
    private function assertRoomForOneMore(): void
    {
        $account = $this->account();
        if ($this->users->countActiveAssistants($account) >= $account->getMaxAssistants()) {
            throw ApiException::conflict('assistant_limit_reached', sprintf('This consultant can have at most %d active assistants.', $account->getMaxAssistants()));
        }
    }

    private function member(string $id): User
    {
        return $this->found($this->users->findTeamMember($this->account(), $id));
    }
}
