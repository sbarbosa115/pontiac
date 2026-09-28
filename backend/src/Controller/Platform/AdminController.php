<?php

declare(strict_types=1);

namespace App\Controller\Platform;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\Input\InviteTeamMemberInput;
use App\Api\InputMapper;
use App\Api\Output\SuperAdminOutput;
use App\Api\Presenter;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\InvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** Configuración › Administradores: Pontiac's own administrators, who invite each other. */
#[Route('/api/platform/admins', name: 'api_platform_admin_')]
final class AdminController extends ApiController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly InvitationService $invitations,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** ?q= searches name and email. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(SuperAdminOutput::class, page: true)]
    public function list(Request $request): JsonResponse
    {
        $viewer = $this->appUser();

        return $this->page(
            $this->users->searchSuperAdmins($request->query->getString('q'), $this->pagination($request)),
            static fn (object $user) => Presenter::superAdmin($user, $viewer),
        );
    }

    #[Route('', name: 'invite', methods: ['POST'])]
    #[ApiResponse(SuperAdminOutput::class, status: 201)]
    public function invite(Request $request, InputMapper $input): JsonResponse
    {
        $data = $input->map($input->json($request), InviteTeamMemberInput::class);
        $user = $this->invitations->inviteSuperAdmin((string) $data->email, trim((string) $data->fullName));

        return $this->json(Presenter::superAdmin($user, $this->appUser()), 201);
    }

    #[Route('/{id}/resend-invitation', name: 'resend', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(SuperAdminOutput::class)]
    public function resend(string $id): JsonResponse
    {
        return $this->json(Presenter::superAdmin($this->invitations->resend($this->admin($id)), $this->appUser()));
    }

    /** Never yourself: the platform always keeps the person doing this. */
    #[Route('/{id}', name: 'disable', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[ApiResponse(SuperAdminOutput::class)]
    public function disable(string $id): JsonResponse
    {
        $admin = $this->admin($id);
        if ($admin->getId()->equals($this->appUser()->getId())) {
            throw ApiException::conflict('cannot_disable_yourself', 'You cannot disable your own access.');
        }
        $admin->setActive(false);
        $this->em->flush();

        return $this->json(Presenter::superAdmin($admin, $this->appUser()));
    }

    #[Route('/{id}/enable', name: 'enable', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(SuperAdminOutput::class)]
    public function enable(string $id): JsonResponse
    {
        $admin = $this->admin($id)->setActive(true);
        $this->em->flush();

        return $this->json(Presenter::superAdmin($admin, $this->appUser()));
    }

    private function admin(string $id): User
    {
        return $this->found($this->users->findSuperAdmin($id));
    }
}
