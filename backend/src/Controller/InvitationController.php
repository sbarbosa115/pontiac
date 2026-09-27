<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\Input\AcceptInvitationInput;
use App\Api\Input\InvitationTokenInput;
use App\Api\InputMapper;
use App\Api\Output\InvitationOutput;
use App\Entity\User;
use App\Security\InvitationService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public endpoints behind the emailed invitation link. The token travels in the body, not the URL, so it does not
 * end up in access logs.
 */
#[Route('/api/invitations', name: 'api_invitation_')]
final class InvitationController extends ApiController
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly InputMapper $input,
    ) {
    }

    #[Route('/lookup', name: 'lookup', methods: ['POST'])]
    #[ApiResponse(InvitationOutput::class)]
    public function lookup(Request $request): JsonResponse
    {
        $token = (string) $this->input->map($this->input->json($request), InvitationTokenInput::class)->token;
        $user = $this->invitations->findPending($token) ?? throw ApiException::notFound();

        return $this->json(new InvitationOutput(
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            role: match (true) {
                $user->hasRole(User::ROLE_CLIENT) => 'client',
                $user->hasRole(User::ROLE_ASSISTANT) => 'assistant',
                $user->hasRole(User::ROLE_OWNER) => 'owner',
                default => 'super_admin',
            },
            accountName: $user->getAccount()?->getName(),
            accountSlug: $user->getAccount()?->getSlug(),
        ));
    }

    #[Route('/accept', name: 'accept', methods: ['POST'])]
    public function accept(
        Request $request,
        #[Autowire(service: 'limiter.invitation_accept')]
        RateLimiterFactoryInterface $limiter,
    ): Response {
        if (!$limiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            throw ApiException::tooManyRequests('too_many_attempts', 'Too many attempts. Try again in a few minutes.');
        }

        $input = $this->input->map($this->input->json($request), AcceptInvitationInput::class);
        $user = $this->invitations->findPending((string) $input->token) ?? throw ApiException::notFound();
        $this->invitations->accept($user, (string) $input->password);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
