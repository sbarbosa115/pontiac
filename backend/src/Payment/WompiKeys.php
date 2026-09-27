<?php

declare(strict_types=1);

namespace App\Payment;

use App\Api\Input\WompiSettingsInput;
use App\Api\ApiValidationException;
use App\Entity\WompiSettings;
use App\Security\SecretBox;

/** Saves and reads a consultant's Wompi keys: secrets encrypted on the way in, decrypted only on the server. */
final class WompiKeys
{
    private const SECRETS = [
        'privateKey' => ['/^prv_(test|prod)_\w+$/', 'Paste the private key: it starts with "prv_test_" or "prv_prod_".'],
        'eventsSecret' => ['/^(test|prod)_events_\w+$/', 'Paste the events secret: it starts with "test_events_" or "prod_events_".'],
        'integritySecret' => ['/^(test|prod)_integrity_\w+$/', 'Paste the integrity secret: it starts with "test_integrity_" or "prod_integrity_".'],
    ];

    public function __construct(private readonly SecretBox $box)
    {
    }

    /**
     * The public key always; each secret only when given (empty keeps what is stored). Every key must be of the same
     * kind: all test, or all production.
     */
    public function apply(WompiSettings $settings, WompiSettingsInput $input): WompiSettings
    {
        $violations = [];
        $publicKey = trim((string) $input->publicKey);
        if (1 !== preg_match('/^pub_(test|prod)_\w+$/', $publicKey)) {
            $violations[] = ['field' => 'publicKey', 'message' => 'Paste the public key: it starts with "pub_test_" or "pub_prod_".'];
        }
        $given = [];
        foreach (self::SECRETS as $name => [$pattern, $message]) {
            $value = null === $input->{$name} ? null : trim((string) $input->{$name});
            if (null === $value || '' === $value) {
                continue;
            }
            if (1 !== preg_match($pattern, $value)) {
                $violations[] = ['field' => $name, 'message' => $message];
                continue;
            }
            $given[$name] = $value;
        }
        if ([] === $violations) {
            $mode = WompiSettings::modeOf($publicKey);
            foreach ($given as $name => $value) {
                if (WompiSettings::modeOf($value) !== $mode) {
                    $violations[] = ['field' => $name, 'message' => 'Use keys of one kind: all test keys or all production keys.'];
                }
            }
            // Switching between test and production: a secret kept from before would be of the other kind.
            if (null !== $settings->getMode() && $settings->getMode() !== $mode) {
                foreach (array_keys(self::SECRETS) as $name) {
                    if (null !== $settings->getEnding($name) && !isset($given[$name])) {
                        $violations[] = ['field' => $name, 'message' => 'Switching between test and production needs this key again.'];
                    }
                }
            }
        }
        if ([] !== $violations) {
            throw new ApiValidationException($violations);
        }

        $settings->setPublicKey($publicKey);
        foreach ($given as $name => $value) {
            $settings->setSecret($name, $this->box->encrypt($value), $value);
        }

        return $settings;
    }

    /**
     * @param 'privateKey'|'eventsSecret'|'integritySecret' $name
     */
    public function secret(WompiSettings $settings, string $name): string
    {
        $sealed = $settings->getSealedSecret($name);

        return '' === $sealed ? '' : $this->box->decrypt($sealed);
    }
}
