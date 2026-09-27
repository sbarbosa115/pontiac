<?php

declare(strict_types=1);

namespace App\Api\Input;

use App\Entity\Account;
use App\Enum\AccountFeature;
use App\Enum\PageTemplate;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /api/platform/settings: what the super admin changed in one tab of Configuración. A field left out (or null)
 * is left as it is.
 */
final class PlatformSettingsInput
{
    #[Assert\Length(min: 1, max: 80)]
    public ?string $platformName = null;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $supportEmail = null;

    #[Assert\Length(min: 1, max: 80)]
    public ?string $senderName = null;

    /** @var list<string>|null PageTemplate values */
    #[Assert\Choice(callback: [PageTemplate::class, 'values'], multiple: true, message: 'Choose among the templates offered.')]
    public ?array $enabledTemplates = null;

    /** @var list<int>|null hours before a session */
    #[Assert\Count(min: 1, max: 3, minMessage: 'Keep at least one reminder.', maxMessage: 'At most three reminders.')]
    #[Assert\All([new Assert\Type('int'), new Assert\Range(min: 1, max: 168)])]
    public ?array $reminderHours = null;

    #[Assert\Range(min: 0, max: 168)]
    public ?int $minNoticeHours = null;

    #[Assert\Range(min: 1, max: 365)]
    public ?int $bookingWindowDays = null;

    #[Assert\Range(min: 0, max: 168)]
    public ?int $clientCancelHours = null;

    #[Assert\Range(min: 0, max: 120)]
    public ?int $sessionBufferMinutes = null;

    #[Assert\Range(min: 0, max: 1000)]
    public ?int $defaultMaxPublishedPages = null;

    #[Assert\Range(min: 0, max: 100)]
    public ?int $defaultMaxAssistants = null;

    #[Assert\Range(min: 0, max: 1000000)]
    public ?int $defaultStorageMb = null;

    #[Assert\Range(min: 1, max: 100)]
    public ?int $defaultMaxFileMb = null;

    /** @var list<string>|null AccountFeature values */
    #[Assert\Choice(callback: [AccountFeature::class, 'values'], multiple: true, message: 'Choose among the features offered.')]
    public ?array $defaultFeatures = null;

    #[Assert\Length(max: 60000)]
    public ?string $termsText = null;

    #[Assert\Length(max: 60000)]
    public ?string $defaultPrivacyText = null;

    /** @var list<string>|null */
    #[Assert\Count(max: 200)]
    #[Assert\All([new Assert\Regex('/^'.Account::SLUG_PATTERN.'$/', message: 'Use 3 to 60 lowercase letters, numbers and hyphens.')])]
    public ?array $reservedSlugs = null;
}
