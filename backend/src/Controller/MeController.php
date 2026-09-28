<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\ApiResponse;
use App\Api\Output\ImpersonatorOutput;
use App\Api\Output\MeOutput;
use App\Api\Presenter;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[ApiResponse(MeOutput::class)]
    public function __invoke(#[CurrentUser] User $user, Security $security): JsonResponse
    {
        $account = $user->getAccount();
        $token = $security->getToken();
        $impersonator = $token instanceof SwitchUserToken ? $token->getOriginalToken()->getUser() : null;

        return $this->json(new MeOutput(
            id: (string) $user->getId(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            roles: $user->getRoles(),
            // Drives currency and date formatting in the UI.
            account: null === $account ? null : Presenter::meAccount($account),
            // The theme is the person's at the screen, so a super admin keeps theirs while acting as someone.
            uiTheme: ($impersonator instanceof User ? $impersonator : $user)->uiTheme()->value,
            impersonator: !$impersonator instanceof User ? null : new ImpersonatorOutput(
                id: (string) $impersonator->getId(),
                email: $impersonator->getEmail(),
                fullName: $impersonator->getFullName(),
            ),
        ));
    }
}
