<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Enum\AccountFeature;
use App\Tests\Support\FakeWompi;

/**
 * Ajustes › Pagos Wompi: the owner's keys. Secrets are stored encrypted and never come back; the screen knows them by
 * their last four characters. Test and production keys are never mixed.
 */
final class WompiSettingsTest extends ApiTestCase
{
    private const KEYS = [
        'publicKey' => 'pub_test_abc123',
        'privateKey' => 'prv_test_privada9876',
        'eventsSecret' => 'test_events_eventos5555',
        'integritySecret' => 'test_integrity_integridad4321',
    ];

    public function testTheOwnerSavesKeysThatNeverComeBack(): void
    {
        $account = $this->createAccount();
        $this->actAs($this->createOwner($account));

        $empty = $this->api('GET', '/api/admin/wompi');
        self::assertSame(['', null, false], [$empty['publicKey'], $empty['mode'], $empty['configured']]);
        self::assertSame('http://localhost:8080/webhooks/wompi/'.$account->getId(), $empty['eventsUrl']);

        $saved = $this->api('PUT', '/api/admin/wompi', self::KEYS);
        self::assertSame(200, $this->responseStatus());
        self::assertSame(['pub_test_abc123', 'test', '9876', '5555', '4321', true], [$saved['publicKey'], $saved['mode'], $saved['privateKeyEnding'], $saved['eventsSecretEnding'], $saved['integritySecretEnding'], $saved['configured']]);
        $raw = (string) $this->client->getResponse()->getContent();
        foreach (['prv_test_privada', 'test_events_eventos', 'test_integrity_integridad'] as $secret) {
            self::assertStringNotContainsString($secret, $raw);
        }
        $stored = (string) json_encode($this->em()->getConnection()->fetchAssociative('SELECT * FROM wompi_settings'));
        self::assertStringNotContainsString('eventos5555', $stored, 'encrypted at rest');

        // Changing only the public key keeps the secrets.
        $again = $this->api('PUT', '/api/admin/wompi', ['publicKey' => 'pub_test_otra', 'eventsSecret' => '']);
        self::assertSame(['pub_test_otra', '5555', true], [$again['publicKey'], $again['eventsSecretEnding'], $again['configured']]);
    }

    public function testKeysMustBeWompisAndOfOneKind(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es');
        $this->actAs($this->createOwner($this->createAccount()));

        $error = $this->api('PUT', '/api/admin/wompi', ['publicKey' => 'mi-llave', 'eventsSecret' => 'secreto']);
        self::assertSame(422, $this->responseStatus());
        self::assertSame(['publicKey', 'eventsSecret'], array_column($error['violations'], 'field'));
        self::assertStringStartsWith('Pega la llave pública', $error['violations'][0]['message']);

        $error = $this->api('PUT', '/api/admin/wompi', ['publicKey' => 'pub_prod_abc', 'integritySecret' => 'test_integrity_x']);
        self::assertSame([['field' => 'integritySecret', 'message' => 'Usa llaves de un solo tipo: todas de pruebas o todas de producción.']], $error['violations']);

        $this->api('PUT', '/api/admin/wompi', self::KEYS);
        $error = $this->api('PUT', '/api/admin/wompi', ['publicKey' => 'pub_prod_abc', 'privateKey' => 'prv_prod_x']);
        self::assertSame(['eventsSecret', 'integritySecret'], array_column($error['violations'], 'field'), 'to production, every secret again');
    }

    public function testTheConnectionTestAsksWompiForTheMerchant(): void
    {
        $this->actAs($this->createOwner($this->createAccount()));
        self::assertSame('payments_not_configured', $this->api('POST', '/api/admin/wompi/test')['error']);
        $this->api('PUT', '/api/admin/wompi', self::KEYS);

        self::assertSame('wompi_rejected', $this->api('POST', '/api/admin/wompi/test')['error']);

        FakeWompi::$merchants['pub_test_abc123'] = ['name' => 'Finanzas Claras SAS'];
        self::assertSame(['merchantName' => 'Finanzas Claras SAS', 'mode' => 'test'], $this->api('POST', '/api/admin/wompi/test'));
        self::assertContains('https://sandbox.wompi.co/v1/merchants/pub_test_abc123', FakeWompi::$requests);
    }

    public function testOnlyTheOwnerWithPaymentsOnSeesThem(): void
    {
        $account = $this->createAccount();
        $account->setFeatures([AccountFeature::Booking]);
        $this->save($account);
        $owner = $this->createOwner($account);
        $this->actAs($this->createAssistant($account));
        $this->api('GET', '/api/admin/wompi');
        self::assertSame(403, $this->responseStatus());

        $this->actAs($owner);
        self::assertSame('feature_disabled', $this->api('GET', '/api/admin/wompi')['error']);
    }
}
