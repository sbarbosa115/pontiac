<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The JWT bundle answers a failed sign-in and a missing, invalid or expired token itself; this gives those answers
 * the API's error shape ({"error": "<code>", "message"}), so the UI reads every 401 the same way.
 */
final class JwtFailureListener
{
    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function onSignInFailed(AuthenticationFailureEvent $event): void
    {
        $event->setResponse(self::unauthorized('invalid_credentials', 'Invalid credentials.'));
    }

    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onTokenMissing(JWTNotFoundEvent $event): void
    {
        $event->setResponse(self::unauthorized('unauthorized', 'Sign in to continue.'));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onTokenInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse(self::unauthorized('unauthorized', 'Sign in to continue.'));
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onTokenExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse(self::unauthorized('session_expired', 'Your session expired. Sign in again.'));
    }

    private static function unauthorized(string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message], 401);
    }
}
