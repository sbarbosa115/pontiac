<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment;

use App\Payment\WompiSignature;
use PHPUnit\Framework\TestCase;

/**
 * Wompi's signatures, with the examples of its documentation: what is concatenated, in which order.
 */
final class WompiSignatureTest extends TestCase
{
    public function testTheIntegritySignatureIsReferenceAmountCurrencyAndSecret(): void
    {
        self::assertSame(
            hash('sha256', 'sk8-438k4-xmxm392-sn2m2490000COPprod_integrity_Z5mMke9x0k8gpErbDqwrJXMqsI6SFli6'),
            WompiSignature::integrity('sk8-438k4-xmxm392-sn2m', 2490000, 'COP', 'prod_integrity_Z5mMke9x0k8gpErbDqwrJXMqsI6SFli6'),
        );
    }

    public function testAnEventIsAuthenticWhenItsPropertiesTimestampAndSecretMatchItsChecksum(): void
    {
        $event = [
            'event' => 'transaction.updated',
            'data' => ['transaction' => ['id' => '1234-1610641025-49201', 'amount_in_cents' => 4490000, 'status' => 'APPROVED']],
            'signature' => [
                'properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'],
                'checksum' => strtoupper(hash('sha256', '1234-1610641025-49201APPROVED44900001530291411prod_events_OcHnIzeBl5socpwByQ4hA52Em3USQ93Z')),
            ],
            'timestamp' => 1530291411,
        ];

        self::assertTrue(WompiSignature::isAuthentic($event, 'prod_events_OcHnIzeBl5socpwByQ4hA52Em3USQ93Z'), 'Wompi may send the checksum in upper case');
        self::assertFalse(WompiSignature::isAuthentic($event, 'prod_events_otro'), 'another secret');

        $event['data']['transaction']['amount_in_cents'] = 100;
        self::assertFalse(WompiSignature::isAuthentic($event, 'prod_events_OcHnIzeBl5socpwByQ4hA52Em3USQ93Z'), 'a changed amount');
    }

    public function testAnEventWithoutASignatureIsNeverAuthentic(): void
    {
        self::assertFalse(WompiSignature::isAuthentic(['data' => ['transaction' => ['id' => 'x']]], 'secret'));
        self::assertFalse(WompiSignature::isAuthentic(['data' => [], 'signature' => ['properties' => ['transaction.id'], 'checksum' => 'x'], 'timestamp' => 1], 'secret'), 'a property that is not there');
    }
}
