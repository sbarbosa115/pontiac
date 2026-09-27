<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AccountFeature;
use App\Enum\PageTemplate;
use App\Repository\PlatformSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Pontiac's own settings, one record, changed only by a super admin (Configuración). The defaults are copied into a
 * consultant's account when it is created (AccountCreator), so changing one never changes an existing consultant.
 * Until a super admin saves them the first time, the values below are the settings (there is no row yet).
 */
#[ORM\Entity(repositoryClass: PlatformSettingsRepository::class)]
class PlatformSettings
{
    use HasUuid;

    #[ORM\Column(length: 80)]
    private string $platformName = 'Pontiac';

    // Reply-To of every email Pontiac sends, and where the public pages send questions about the platform.
    #[ORM\Column(length: 180)]
    private string $supportEmail = 'soporte@pontiac.co';

    // The name emails come from when no consultant sends them. The address itself is the server's (MAILER_FROM).
    #[ORM\Column(length: 80)]
    private string $senderName = 'Pontiac';

    /** @var list<string> PageTemplate values a new page may use */
    #[ORM\Column(type: Types::JSON)]
    private array $enabledTemplates;

    // Booking defaults for a new consultant; milestone 2 copies them into the consultant's booking settings.
    /** @var list<int> hours before a session */
    #[ORM\Column(type: Types::JSON)]
    private array $reminderHours = [24, 1];

    #[ORM\Column(type: Types::SMALLINT)]
    private int $minNoticeHours = 12;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $bookingWindowDays = 30;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $clientCancelHours = 24;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $sessionBufferMinutes = 15;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $defaultMaxPublishedPages = Account::DEFAULT_MAX_PUBLISHED_PAGES;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $defaultMaxAssistants = Account::DEFAULT_MAX_ASSISTANTS;

    #[ORM\Column]
    private int $defaultStorageMb = Account::DEFAULT_STORAGE_MB;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $defaultMaxFileMb = Account::DEFAULT_MAX_FILE_MB;

    /** @var list<string> AccountFeature values a new consultant starts with */
    #[ORM\Column(type: Types::JSON)]
    private array $defaultFeatures;

    #[ORM\Column(type: Types::TEXT)]
    private string $termsText = '';

    // The privacy policy (Ley 1581) a new consultant starts from; each consultant edits their own.
    #[ORM\Column(type: Types::TEXT)]
    private string $defaultPrivacyText = '';

    /** @var list<string> addresses no consultant may take, besides the app's own (Account::RESERVED_SLUGS) */
    #[ORM\Column(type: Types::JSON)]
    private array $reservedSlugs = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->enabledTemplates = PageTemplate::values();
        $this->defaultFeatures = AccountFeature::values();
    }

    /**
     * Every setting as plain values, keyed as the API names them: what the change log compares.
     *
     * @return array<string, scalar|list<scalar>>
     */
    public function snapshot(): array
    {
        return [
            'platformName' => $this->platformName,
            'supportEmail' => $this->supportEmail,
            'senderName' => $this->senderName,
            'enabledTemplates' => $this->enabledTemplates,
            'reminderHours' => $this->reminderHours,
            'minNoticeHours' => $this->minNoticeHours,
            'bookingWindowDays' => $this->bookingWindowDays,
            'clientCancelHours' => $this->clientCancelHours,
            'sessionBufferMinutes' => $this->sessionBufferMinutes,
            'defaultMaxPublishedPages' => $this->defaultMaxPublishedPages,
            'defaultMaxAssistants' => $this->defaultMaxAssistants,
            'defaultStorageMb' => $this->defaultStorageMb,
            'defaultMaxFileMb' => $this->defaultMaxFileMb,
            'defaultFeatures' => $this->defaultFeatures,
            'termsText' => $this->termsText,
            'defaultPrivacyText' => $this->defaultPrivacyText,
            'reservedSlugs' => $this->reservedSlugs,
        ];
    }

    public function getPlatformName(): string
    {
        return $this->platformName;
    }

    public function getSupportEmail(): string
    {
        return $this->supportEmail;
    }

    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function setGeneral(string $platformName, string $supportEmail, string $senderName): static
    {
        $this->platformName = $platformName;
        $this->supportEmail = mb_strtolower(trim($supportEmail));
        $this->senderName = $senderName;

        return $this;
    }

    /**
     * @return list<PageTemplate>
     */
    public function getEnabledTemplates(): array
    {
        return array_values(array_filter(array_map(PageTemplate::tryFrom(...), $this->enabledTemplates)));
    }

    /**
     * @param list<PageTemplate> $templates
     */
    public function setEnabledTemplates(array $templates): static
    {
        $values = array_map(static fn (PageTemplate $t) => $t->value, $templates);
        $this->enabledTemplates = array_values(array_intersect(PageTemplate::values(), $values));

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getReminderHours(): array
    {
        return $this->reminderHours;
    }

    public function getMinNoticeHours(): int
    {
        return $this->minNoticeHours;
    }

    public function getBookingWindowDays(): int
    {
        return $this->bookingWindowDays;
    }

    public function getClientCancelHours(): int
    {
        return $this->clientCancelHours;
    }

    public function getSessionBufferMinutes(): int
    {
        return $this->sessionBufferMinutes;
    }

    /**
     * @param list<int> $reminderHours
     */
    public function setBookingDefaults(array $reminderHours, int $minNoticeHours, int $bookingWindowDays, int $clientCancelHours, int $sessionBufferMinutes): static
    {
        // Largest first, each once: "24 h and 1 h before".
        $hours = array_values(array_unique($reminderHours));
        rsort($hours);
        $this->reminderHours = $hours;
        $this->minNoticeHours = $minNoticeHours;
        $this->bookingWindowDays = $bookingWindowDays;
        $this->clientCancelHours = $clientCancelHours;
        $this->sessionBufferMinutes = $sessionBufferMinutes;

        return $this;
    }

    public function getDefaultMaxPublishedPages(): int
    {
        return $this->defaultMaxPublishedPages;
    }

    public function getDefaultMaxAssistants(): int
    {
        return $this->defaultMaxAssistants;
    }

    public function getDefaultStorageMb(): int
    {
        return $this->defaultStorageMb;
    }

    public function getDefaultMaxFileMb(): int
    {
        return $this->defaultMaxFileMb;
    }

    public function setDefaultLimits(int $maxPublishedPages, int $maxAssistants, int $storageMb, int $maxFileMb): static
    {
        $this->defaultMaxPublishedPages = $maxPublishedPages;
        $this->defaultMaxAssistants = $maxAssistants;
        $this->defaultStorageMb = $storageMb;
        $this->defaultMaxFileMb = $maxFileMb;

        return $this;
    }

    /**
     * @return list<AccountFeature>
     */
    public function getDefaultFeatures(): array
    {
        return array_values(array_filter(array_map(AccountFeature::tryFrom(...), $this->defaultFeatures)));
    }

    /**
     * @param list<AccountFeature> $features
     */
    public function setDefaultFeatures(array $features): static
    {
        $values = array_map(static fn (AccountFeature $f) => $f->value, $features);
        $this->defaultFeatures = array_values(array_intersect(AccountFeature::values(), $values));

        return $this;
    }

    public function getTermsText(): string
    {
        return $this->termsText;
    }

    public function getDefaultPrivacyText(): string
    {
        return $this->defaultPrivacyText;
    }

    /**
     * @return list<string>
     */
    public function getReservedSlugs(): array
    {
        return $this->reservedSlugs;
    }

    /**
     * @param list<string> $reservedSlugs
     */
    public function setLegal(string $termsText, string $defaultPrivacyText, array $reservedSlugs): static
    {
        $this->termsText = $termsText;
        $this->defaultPrivacyText = $defaultPrivacyText;
        $slugs = array_values(array_unique(array_filter(array_map(Account::normalizeSlug(...), $reservedSlugs))));
        sort($slugs);
        $this->reservedSlugs = $slugs;

        return $this;
    }

    public function isReservedSlug(string $slug): bool
    {
        return Account::isReservedSlug($slug) || \in_array(Account::normalizeSlug($slug), $this->reservedSlugs, true);
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
