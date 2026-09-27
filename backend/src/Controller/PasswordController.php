<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\ApiValidationException;
use App\Api\Input\ChangePasswordInput;
use App\Api\Input\PasswordResetInput;
use App\Api\Input\PasswordResetRequestInput;
use App\Api\InputMapper;
use App\Api\Output\PasswordResetOutput;
use App\Security\PasswordReset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Passwords: "¿Olvidaste tu contraseña?" (anyone: ask for a link, then set a new one with it) and changing one's own
 * (signed in, knowing the current one).
 */
final class PasswordController extends ApiController
{
    public function __construct(
        private readonly InputMapper $input,
        private readonly PasswordReset $resets,
        #[Autowire(service: 'limiter.password_reset')]
        private readonly RateLimiterFactoryInterface $limiter,
    ) {
    }

    /** Always 204, whether the email has an account or not. */
    #[Route('/api/password-reset/request', name: 'api_password_reset_request', methods: ['POST'])]
    public function request(Request $request): Response
    {
        $this->limit($request);
        $data = $this->input->map($this->input->json($request), PasswordResetRequestInput::class);
        $this->resets->request((string) $data->email, null === $data->account || '' === $data->account ? null : $data->account);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/password-reset/confirm', name: 'api_password_reset_confirm', methods: ['POST'])]
    #[ApiResponse(PasswordResetOutput::class)]
    public function confirm(Request $request): JsonResponse
    {
        $this->limit($request);
        $data = $this->input->map($this->input->json($request), PasswordResetInput::class);
        $user = $this->resets->findValid((string) $data->token) ?? throw ApiException::notFound();

        return $this->json(new PasswordResetOutput(loginPath: $this->resets->reset($user, (string) $data->password)));
    }

    /** "Mi cuenta › Cambiar contraseña". */
    #[Route('/api/me/password', name: 'api_me_password', methods: ['POST'])]
    public function change(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $em): Response
    {
        $this->limit($request);
        $data = $this->input->map($this->input->json($request), ChangePasswordInput::class);
        $user = $this->appUser();
        if (!$hasher->isPasswordValid($user, (string) $data->currentPassword)) {
            throw ApiValidationException::single('currentPassword', 'That is not your current password.');
        }
        $user->changePassword($hasher->hashPassword($user, (string) $data->newPassword));
        $em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function limit(Request $request): void
    {
        if (!$this->limiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            throw ApiException::tooManyRequests('too_many_attempts', 'Too many attempts. Try again in a few minutes.');
        }
    }
}
