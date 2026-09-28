<?php

declare(strict_types=1);

namespace App\Payment;

/**
 * Wompi's two signatures (docs.wompi.co): the integrity signature a checkout carries, and the checksum of the events
 * Wompi sends us.
 */
final class WompiSignature
{
    /** SHA-256 of reference + amount in cents + currency + integrity secret. */
    public static function integrity(string $reference, int $amountInCents, string $currency, string $integritySecret): string
    {
        return hash('sha256', $reference.$amountInCents.$currency.$integritySecret);
    }

    /**
     * SHA-256 of the values of `signature.properties` (paths into `data`, in their order) + `timestamp` + the events
     * secret, compared with `signature.checksum` in constant time.
     *
     * @param array<string, mixed> $event
     */
    public static function isAuthentic(array $event, string $eventsSecret): bool
    {
        $expected = self::checksum($event, $eventsSecret);
        $given = $event['signature']['checksum'] ?? null;

        return null !== $expected && \is_string($given) && hash_equals($expected, strtolower($given));
    }

    /**
     * @param array<string, mixed> $event
     */
    public static function checksum(array $event, string $eventsSecret): ?string
    {
        $properties = $event['signature']['properties'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        if (!\is_array($properties) || [] === $properties || !(\is_int($timestamp) || \is_string($timestamp))) {
            return null;
        }
        $concatenated = '';
        foreach ($properties as $path) {
            $value = $event['data'] ?? null;
            foreach (explode('.', (string) $path) as $key) {
                $value = \is_array($value) ? ($value[$key] ?? null) : null;
            }
            if (!\is_scalar($value)) {
                return null;
            }
            $concatenated .= (string) $value;
        }

        return hash('sha256', $concatenated.$timestamp.$eventsSecret);
    }
}
