<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts the secrets a consultant gives us (Wompi's keys) with libsodium's secretbox, the key from the environment
 * (`APP_ENCRYPTION_KEY`, 32 bytes in base64: `php -r 'echo base64_encode(random_bytes(32));'`). What is stored is
 * the nonce and the ciphertext, in base64; changing the key makes stored secrets unreadable.
 */
final class SecretBox
{
    private readonly string $key;

    public function __construct(#[Autowire('%env(APP_ENCRYPTION_KEY)%')] string $key)
    {
        $decoded = base64_decode($key, true);
        if (false === $decoded || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($decoded)) {
            throw new \InvalidArgumentException('APP_ENCRYPTION_KEY must be 32 bytes in base64.');
        }
        $this->key = $decoded;
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $sealed): string
    {
        $raw = base64_decode($sealed, true);
        $plain = false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
            ? false
            : sodium_crypto_secretbox_open(substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);

        return false === $plain ? throw new \RuntimeException('A stored secret could not be decrypted (was APP_ENCRYPTION_KEY changed?).') : $plain;
    }
}
