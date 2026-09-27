<?php

declare(strict_types=1);

namespace App\Api;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * An expected API error. The machine-readable $errorCode is what the UI maps
 * to a localized message; the English message is for developers.
 */
final class ApiException extends \RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self(404, 'not_found', 'Resource not found.');
    }

    public static function conflict(string $errorCode, string $message): self
    {
        return new self(409, $errorCode, $message);
    }

    public static function badRequest(string $errorCode, string $message): self
    {
        return new self(400, $errorCode, $message);
    }

    /**
     * Credentials were missing or wrong (a sign-in endpoint of our own; the firewalls answer 401 themselves).
     */
    public static function unauthorized(string $errorCode, string $message): self
    {
        return new self(401, $errorCode, $message);
    }

    public static function forbidden(string $errorCode, string $message): self
    {
        return new self(403, $errorCode, $message);
    }

    /**
     * The request is well formed but cannot be carried out as it stands (a channel that is not available, keys
     * the provider refused). Unlike a validation error it names no field.
     */
    public static function unprocessable(string $errorCode, string $message): self
    {
        return new self(422, $errorCode, $message);
    }

    public static function tooManyRequests(string $errorCode, string $message): self
    {
        return new self(429, $errorCode, $message);
    }

    /**
     * The server is missing something it needs (its configuration), not the request.
     */
    public static function unavailable(string $errorCode, string $message): self
    {
        return new self(503, $errorCode, $message);
    }

    /**
     * Something outside Pontiac (a provider's API, such as Wompi) did not answer.
     */
    public static function badGateway(string $errorCode, string $message): self
    {
        return new self(502, $errorCode, $message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, mixed> */
    public function getHeaders(): array
    {
        return [];
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
