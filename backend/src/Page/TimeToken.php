<?php

declare(strict_types=1);

namespace App\Page;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * When a form was shown, signed: a form sent back seconds after it was drawn, or with a token nobody signed, was
 * filled in by a script (LeadIntake quietly drops it).
 */
final class TimeToken
{
    public const MIN_SECONDS = 3;
    public const MAX_SECONDS = 86400;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function issue(?int $at = null): string
    {
        $at ??= time();

        return $at.'.'.hash_hmac('sha256', (string) $at, $this->secret);
    }

    public function isHuman(string $token, ?int $now = null): bool
    {
        [$at, $signature] = array_pad(explode('.', $token, 2), 2, '');
        if (!ctype_digit($at) || !hash_equals(hash_hmac('sha256', $at, $this->secret), $signature)) {
            return false;
        }
        $elapsed = ($now ?? time()) - (int) $at;

        return $elapsed >= self::MIN_SECONDS && $elapsed <= self::MAX_SECONDS;
    }
}
