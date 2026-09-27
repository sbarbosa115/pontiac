<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AccountFeature;
use App\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A consultant's practice: Pontiac's customer. Everything the consultant, their assistants and their clients work
 * with belongs to one account (AccountOwnedInterface). Its slug is the first segment of every public URL
 * (pontiac.co/<slug>, pontiac.co/<slug>/<page>, pontiac.co/<slug>/portal).
 *
 * Country, currency, locale and timezone are settings of the account, so nothing in the model assumes Colombia.
 */
#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[UniqueEntity('slug', message: 'This address is already taken.')]
class Account
{
    use HasUuid;

    /**
     * First path segments the app itself uses: an account cannot take them as its slug.
     */
    public const RESERVED_SLUGS = [
        'admin', 'api', 'assets', 'build', 'bundles', 'favicon.ico', 'help', 'invitacion', 'login', 'logout', 'media',
        'pago', 'plataforma', 'portal', 'pontiac', 'privacidad', 'public', 'reservar', 'robots.txt', 'sitemap.xml',
        'soporte', 'static', 'terminos', 'uploads', 'www',
    ];

    // What a consultant starts with when nobody says otherwise (the platform's defaults are these until changed).
    public const DEFAULT_MAX_PUBLISHED_PAGES = 10;
    public const DEFAULT_MAX_ASSISTANTS = 3;
    public const DEFAULT_STORAGE_MB = 1024;
    public const DEFAULT_MAX_FILE_MB = 10;

    public const SLUG_PATTERN = '[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(length: 60, unique: true)]
    #[Assert\Regex('/^'.self::SLUG_PATTERN.'$/', message: 'Use 3 to 60 lowercase letters, numbers and hyphens.')]
    private string $slug;

    #[ORM\Column(length: 2)]
    #[Assert\Country]
    private string $country;

    #[ORM\Column(length: 3)]
    #[Assert\Currency]
    private string $currency;

    #[ORM\Column(length: 16)]
    #[Assert\Locale]
    private string $locale;

    #[ORM\Column(length: 64)]
    #[Assert\Timezone]
    private string $timezone;

    // A suspended account's people cannot sign in, and its public pages are not served.
    #[ORM\Column]
    private bool $active = true;

    // What the super admin turned on (AccountFeature values).
    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $features;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $maxPublishedPages = self::DEFAULT_MAX_PUBLISHED_PAGES;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $maxAssistants = self::DEFAULT_MAX_ASSISTANTS;

    #[ORM\Column]
    private int $storageMb = self::DEFAULT_STORAGE_MB;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $maxFileMb = self::DEFAULT_MAX_FILE_MB;

    // The privacy policy (Ley 1581) the consultant's forms link to. Empty: the platform's default text is shown.
    #[ORM\Column(type: Types::TEXT)]
    private string $privacyText = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $slug, string $country = 'CO', string $currency = 'COP', string $locale = 'es_CO', string $timezone = 'America/Bogota')
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->slug = self::normalizeSlug($slug);
        $this->country = strtoupper($country);
        $this->currency = strtoupper($currency);
        $this->locale = $locale;
        $this->timezone = $timezone;
        $this->features = AccountFeature::values();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function normalizeSlug(string $slug): string
    {
        return mb_strtolower(trim($slug));
    }

    public static function isReservedSlug(string $slug): bool
    {
        return \in_array(self::normalizeSlug($slug), self::RESERVED_SLUGS, true);
    }

    #[Assert\IsFalse(message: 'This address is reserved.')]
    public function hasReservedSlug(): bool
    {
        return self::isReservedSlug($this->slug);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = self::normalizeSlug($slug);

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function setCountry(string $country): static
    {
        $this->country = strtoupper($country);

        return $this;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = strtoupper($currency);

        return $this;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function hasFeature(AccountFeature $feature): bool
    {
        return \in_array($feature->value, $this->features, true);
    }

    /**
     * @return list<AccountFeature>
     */
    public function getFeatures(): array
    {
        return array_values(array_filter(array_map(AccountFeature::tryFrom(...), $this->features)));
    }

    /**
     * @param list<AccountFeature> $features
     */
    public function setFeatures(array $features): static
    {
        // Kept in the enum's order, so the stored list does not depend on the order they were ticked in.
        $values = array_map(static fn (AccountFeature $f) => $f->value, $features);
        $this->features = array_values(array_intersect(AccountFeature::values(), $values));

        return $this;
    }

    public function getMaxPublishedPages(): int
    {
        return $this->maxPublishedPages;
    }

    public function getMaxAssistants(): int
    {
        return $this->maxAssistants;
    }

    public function getStorageMb(): int
    {
        return $this->storageMb;
    }

    public function getMaxFileMb(): int
    {
        return $this->maxFileMb;
    }

    public function setLimits(int $maxPublishedPages, int $maxAssistants, int $storageMb, int $maxFileMb): static
    {
        $this->maxPublishedPages = $maxPublishedPages;
        $this->maxAssistants = $maxAssistants;
        $this->storageMb = $storageMb;
        $this->maxFileMb = $maxFileMb;

        return $this;
    }

    public function getPrivacyText(): string
    {
        return $this->privacyText;
    }

    public function setPrivacyText(string $privacyText): static
    {
        $this->privacyText = $privacyText;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
