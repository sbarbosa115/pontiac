<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\Input\PortalLoginInput;
use App\Api\InputMapper;
use App\Api\Output\TokenOutput;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\UserRepository;
use App\Security\UserChecker;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccountStatusException;

/**
 * A client signs in at their consultant's portal (/<slug>/portal) with email and password. The same email can be a
 * client of two consultants, so the consultant's slug says which login is meant; staff use /api/login instead.
 *
 * Every failure is the same 401 invalid_credentials: an unknown consultant, an unknown email, a wrong password and
 * a disabled login look alike, so the form does not tell anyone who is a client of whom.
 */
final class PortalLoginController extends ApiController
{
    #[Route('/api/portal-login', name: 'api_portal_login', methods: ['POST'])]
    #[ApiResponse(TokenOutput::class)]
    public function __invoke(
        Request $request,
        InputMapper $mapper,
        AccountRepository $accounts,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        UserChecker $checker,
        JWTTokenManagerInterface $jwt,
        EntityManagerInterface $em,
        #[Autowire(service: 'limiter.portal_login_ip')]
        RateLimiterFactoryInterface $perIp,
        #[Autowire(service: 'limiter.portal_login_email')]
        RateLimiterFactoryInterface $perEmail,
    ): JsonResponse {
        $input = $mapper->map($mapper->json($request), PortalLoginInput::class);
        $slug = (string) $input->account;
        $email = User::normalizeEmail((string) $input->email);

        if (!$perIp->create((string) $request->getClientIp())->consume()->isAccepted()
            || !$perEmail->create($slug.'|'.$email)->consume()->isAccepted()) {
            throw ApiException::tooManyRequests('too_many_attempts', 'Too many attempts. Try again in a few minutes.');
        }

        $account = $accounts->findActiveBySlug($slug);
        $user = null === $account ? null : $users->findClient($account, $email);
        if (null === $user || null === $user->getPassword() || !$hasher->isPasswordValid($user, (string) $input->password)) {
            throw self::invalid();
        }
        try {
            $checker->checkPreAuth($user);
        } catch (AccountStatusException) {
            throw self::invalid();
        }

        $user->markSignedIn();
        $em->flush();

        return $this->json(new TokenOutput(token: $jwt->create($user)));
    }

    private static function invalid(): ApiException
    {
        return ApiException::unauthorized('invalid_credentials', 'Invalid credentials.');
    }
}
