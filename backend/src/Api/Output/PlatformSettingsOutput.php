<?php

declare(strict_types=1);

namespace App\Api\Output;

/** Pontiac's settings (Configuración). */
final readonly class PlatformSettingsOutput
{
    public function __construct(
        public string $platformName,
        public string $supportEmail,
        public string $senderName,
        /** @var list<string> PageTemplate values a new page may use */
        public array $enabledTemplates,
        /** @var list<int> hours before a session, largest first */
        public array $reminderHours,
        public int $minNoticeHours,
        public int $bookingWindowDays,
        public int $clientCancelHours,
        public int $sessionBufferMinutes,
        public int $defaultMaxPublishedPages,
        public int $defaultMaxAssistants,
        public int $defaultStorageMb,
        public int $defaultMaxFileMb,
        /** @var list<string> AccountFeature values */
        public array $defaultFeatures,
        public string $termsText,
        public string $defaultPrivacyText,
        /** @var list<string> */
        public array $reservedSlugs,
        /** ISO 8601; null until a super admin first saves them. */
        public ?string $updatedAt,
    ) {
    }
}
