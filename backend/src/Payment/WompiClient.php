<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\WompiSettings;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What we ask Wompi's API: a transaction (to confirm a result ourselves) and a merchant (to test the keys). The
 * sandbox or production API follows from the key. In tests the HTTP client is App\Tests\Support\FakeWompi.
 */
final class WompiClient
{
    public const CHECKOUT_URL = 'https://checkout.wompi.co/p/';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public static function apiUrl(string $mode): string
    {
        return WompiSettings::MODE_PRODUCTION === $mode ? 'https://production.wompi.co/v1' : 'https://sandbox.wompi.co/v1';
    }

    /**
     * A transaction as Wompi has it, or null when Wompi does not know it (or cannot be reached).
     *
     * @return array<string, mixed>|null
     */
    public function transaction(string $mode, string $transactionId): ?array
    {
        if (1 !== preg_match('/^[\w-]{1,64}$/', $transactionId)) {
            return null;
        }

        return $this->data(self::apiUrl($mode).'/transactions/'.$transactionId);
    }

    /**
     * The merchant a public key belongs to, or null when Wompi rejects the key (or cannot be reached).
     *
     * @return array<string, mixed>|null
     */
    public function merchant(string $publicKey): ?array
    {
        $mode = WompiSettings::modeOf($publicKey);

        return null === $mode ? null : $this->data(self::apiUrl($mode).'/merchants/'.rawurlencode($publicKey));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function data(string $url): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
            if (200 !== $response->getStatusCode()) {
                return null;
            }
            $data = $response->toArray()['data'] ?? null;
        } catch (ExceptionInterface) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }
}
