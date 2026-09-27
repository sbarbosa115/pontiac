<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * Wompi's API as the tests want it (App\Payment\WompiClient's HTTP client in the test environment): the transactions
 * and merchants a test registers answer 200, anything else 404. Forgotten before every test (ApiTestCase).
 */
final class FakeWompi extends MockHttpClient
{
    /** @var array<string, array<string, mixed>> by transaction id */
    public static array $transactions = [];

    /** @var array<string, array<string, mixed>> by public key */
    public static array $merchants = [];

    /** @var list<string> every URL asked */
    public static array $requests = [];

    public function __construct()
    {
        parent::__construct(static function (string $method, string $url): JsonMockResponse {
            self::$requests[] = $url;
            if (1 === preg_match('#/v1/transactions/([^/?]+)$#', $url, $m) && isset(self::$transactions[$m[1]])) {
                return new JsonMockResponse(['data' => self::$transactions[$m[1]]]);
            }
            if (1 === preg_match('#/v1/merchants/([^/?]+)$#', $url, $m) && isset(self::$merchants[rawurldecode($m[1])])) {
                return new JsonMockResponse(['data' => self::$merchants[rawurldecode($m[1])]]);
            }

            return new JsonMockResponse(['error' => ['type' => 'NOT_FOUND_ERROR']], ['http_code' => 404]);
        });
    }

    public static function forget(): void
    {
        self::$transactions = [];
        self::$merchants = [];
        self::$requests = [];
    }
}
