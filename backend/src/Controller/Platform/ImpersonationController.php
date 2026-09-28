<?php

declare(strict_types=1);

namespace App\Controller\Platform;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Output\ImpersonatableUserOutput;
use App\Api\Presenter;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * ROLE_SUPER_ADMIN only (platform firewall). The consultants and assistants a super admin can act as (the
 * X-Switch-User header on the api firewall), to give support. Not paginated: it feeds a picker.
 */
final class ImpersonationController extends ApiController
{
    #[Route('/api/platform/impersonatable-users', name: 'api_platform_impersonatable_users', methods: ['GET'])]
    #[ApiResponse(ImpersonatableUserOutput::class, list: true, key: 'items')]
    public function __invoke(UserRepository $users): JsonResponse
    {
        return $this->json(['items' => array_map(Presenter::impersonatableUser(...), $users->findImpersonatable())]);
    }
}
