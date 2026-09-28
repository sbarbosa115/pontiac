<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\SecretBox;
use PHPUnit\Framework\TestCase;

/** Consultants' secrets are stored encrypted and come back only with the same key. */
final class SecretBoxTest extends TestCase
{
    public function testASecretComesBackWithTheSameKeyOnly(): void
    {
        $box = new SecretBox(base64_encode(str_repeat('k', 32)));

        $sealed = $box->encrypt('test_events_secreto');
        self::assertStringNotContainsString('secreto', (string) base64_decode($sealed, true));
        self::assertNotSame($sealed, $box->encrypt('test_events_secreto'), 'a new nonce every time');
        self::assertSame('test_events_secreto', $box->decrypt($sealed));

        $this->expectException(\RuntimeException::class);
        (new SecretBox(base64_encode(str_repeat('x', 32))))->decrypt($sealed);
    }

    public function testTheKeyMustBe32Bytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SecretBox(base64_encode('corta'));
    }
}
