<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Component\Mime\Email;

/**
 * Plataforma › Configuración: Pontiac's settings, saved one tab at a time, each save logged with who changed what.
 */
final class SettingsTest extends ApiTestCase
{
    public function testUntilSavedTheSettingsAreTheDefaults(): void
    {
        $this->actAs($this->createSuperAdmin());

        $settings = $this->api('GET', '/api/platform/settings');

        self::assertSame('Pontiac', $settings['platformName']);
        self::assertSame(['free_diagnostic', 'plan_offer', 'consultant_profile', 'event', 'lead_magnet'], $settings['enabledTemplates']);
        self::assertSame([24, 1], $settings['reminderHours']);
        self::assertSame(['booking', 'payments', 'portal', 'flows'], $settings['defaultFeatures']);
        self::assertSame([10, 3, 1024, 10], [$settings['defaultMaxPublishedPages'], $settings['defaultMaxAssistants'], $settings['defaultStorageMb'], $settings['defaultMaxFileMb']]);
        self::assertNull($settings['updatedAt']);
    }

    public function testATabSavesItsFieldsAndLeavesTheRest(): void
    {
        $this->actAs($this->createSuperAdmin());

        $saved = $this->api('PATCH', '/api/platform/settings', ['platformName' => 'Pontiac Pro', 'supportEmail' => 'Ayuda@Pontiac.test', 'reminderHours' => [1, 48, 1], 'reservedSlugs' => ['soporte-vip', 'demo', 'demo']]);

        self::assertSame(200, $this->responseStatus());
        self::assertSame(['Pontiac Pro', 'ayuda@pontiac.test', 'Pontiac', [48, 1], ['demo', 'soporte-vip']], [$saved['platformName'], $saved['supportEmail'], $saved['senderName'], $saved['reminderHours'], $saved['reservedSlugs']]);
        self::assertNotNull($saved['updatedAt']);
        self::assertSame('Pontiac Pro', $this->api('GET', '/api/platform/settings')['platformName'], 'and it stays');
    }

    public function testEverySettingIsValidated(): void
    {
        $this->actAs($this->createSuperAdmin());

        $error = $this->api('PATCH', '/api/platform/settings', [
            'platformName' => '',
            'supportEmail' => 'no-es-un-correo',
            'enabledTemplates' => ['brochure'],
            'reminderHours' => [],
            'bookingWindowDays' => 0,
            'defaultMaxFileMb' => 500,
            'defaultFeatures' => ['teleport'],
            'reservedSlugs' => ['No Vale'],
        ]);

        self::assertSame(422, $this->responseStatus());
        self::assertEqualsCanonicalizing(
            ['platformName', 'supportEmail', 'enabledTemplates', 'reminderHours', 'bookingWindowDays', 'defaultMaxFileMb', 'defaultFeatures', 'reservedSlugs[0]'],
            array_column($error['violations'], 'field'),
        );
    }

    public function testEachSaveIsLoggedWithWhoChangedWhat(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actAs($admin);

        $this->api('PATCH', '/api/platform/settings', ['senderName' => 'Equipo Pontiac', 'enabledTemplates' => ['free_diagnostic', 'plan_offer']]);
        $this->api('PATCH', '/api/platform/settings', ['senderName' => 'Equipo Pontiac']);
        $this->api('PATCH', '/api/platform/settings', ['minNoticeHours' => 24]);

        $history = $this->api('GET', '/api/platform/settings/history');
        self::assertSame(2, $history['total'], 'a save that changes nothing is not logged');
        self::assertSame([['field' => 'minNoticeHours', 'from' => '12', 'to' => '24']], $history['items'][0]['changes']);
        self::assertSame(
            [
                ['field' => 'senderName', 'from' => 'Pontiac', 'to' => 'Equipo Pontiac'],
                ['field' => 'enabledTemplates', 'from' => 'free_diagnostic, plan_offer, consultant_profile, event, lead_magnet', 'to' => 'free_diagnostic, plan_offer'],
            ],
            $history['items'][1]['changes'],
        );
        self::assertSame('Paula Plataforma', $history['items'][0]['changedBy']['fullName']);
    }

    public function testTheSenderNameAndSupportEmailGoOnEveryEmail(): void
    {
        $this->actAs($this->createSuperAdmin());
        $this->api('PATCH', '/api/platform/settings', ['senderName' => 'Equipo Pontiac', 'supportEmail' => 'ayuda@pontiac.test']);

        $this->api('POST', '/api/platform/accounts', ['name' => 'Plata Sana', 'slug' => 'plata-sana', 'ownerName' => 'Paola', 'ownerEmail' => 'paola@demo.test']);

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Equipo Pontiac', $email->getFrom()[0]->getName());
        self::assertSame('ayuda@pontiac.test', $email->getReplyTo()[0]->getAddress());
    }

    public function testOnlyASuperAdminReadsOrChangesThem(): void
    {
        $this->actAs($this->createOwner($this->createAccount()));

        foreach ([['GET', '/api/platform/settings'], ['PATCH', '/api/platform/settings'], ['GET', '/api/platform/settings/history']] as [$method, $path]) {
            $this->api($method, $path, 'GET' === $method ? null : ['platformName' => 'X']);
            self::assertSame(403, $this->responseStatus(), $method.' '.$path);
        }
    }
}
