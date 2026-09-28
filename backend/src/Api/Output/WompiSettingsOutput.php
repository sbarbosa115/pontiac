<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A consultant's Wompi keys as the screen shows them: the secrets only by their last four characters. */
final readonly class WompiSettingsOutput
{
    public function __construct(
        public string $publicKey,
        /** "test" or "production", from the keys; null until there is a public key. */
        public ?string $mode,
        public ?string $privateKeyEnding,
        public ?string $eventsSecretEnding,
        public ?string $integritySecretEnding,
        /** Enough to take payments: public key, events and integrity secrets. */
        public bool $configured,
        /** What to paste in Wompi's dashboard as the events URL. */
        public string $eventsUrl,
    ) {
    }
}
