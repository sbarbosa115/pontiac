<?php

declare(strict_types=1);

namespace App\Api;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Every /api error is JSON: {"error": "<code>", "message": "...", "violations"?: [...]}.
 * The UI translates by "error" code; "message" is for developers. Violation messages are shown to people as they
 * come, so they follow the request's language (Accept-Language).
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthorized',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        415 => 'unsupported_media_type',
        429 => 'too_many_requests',
    ];

    public function __construct(
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Runs after the security exception listener (priority 1) has turned access errors into 401/403.
        return [KernelEvents::EXCEPTION => ['onException', 0]];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $e = $event->getThrowable();

        $response = match (true) {
            $e instanceof ApiValidationException => self::error(422, 'validation_failed', $e->getMessage(), ['violations' => $e->getTranslatedViolations($this->translator)]),
            $e instanceof ApiException => self::error($e->getStatusCode(), $e->getErrorCode(), $e->getMessage()),
            $e instanceof HttpExceptionInterface => self::error(
                $e->getStatusCode(),
                self::CODES[$e->getStatusCode()] ?? 'http_error',
                $e->getMessage() ?: (Response::$statusTexts[$e->getStatusCode()] ?? 'Error'),
                headers: $e->getHeaders(),
            ),
            $e instanceof TransportExceptionInterface => self::error(502, 'email_failed', 'The email could not be sent. Try again later.'),
            $this->debug => null, // keep Symfony's detailed error page in dev
            default => self::error(500, 'internal_error', 'Internal server error.'),
        };

        if (null !== $response) {
            $event->setResponse($response);
        }
    }

    /**
     * @param array<string, mixed>  $extra
     * @param array<string, string> $headers
     */
    private static function error(int $status, string $code, string $message, array $extra = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message] + $extra, $status, $headers);
    }
}
