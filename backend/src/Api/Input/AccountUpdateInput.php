<?php

declare(strict_types=1);

namespace App\Api\Input;

use App\Enum\AccountFeature;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /api/platform/accounts/{id}: what the super admin changed. A field left out (or null) is left as it is:
 * Datos sends the first six, Límites y funciones the rest.
 */
final class AccountUpdateInput
{
    #[Assert\Length(min: 1, max: 180)]
    public ?string $name = null;

    public ?string $slug = null;

    #[Assert\Country]
    public ?string $country = null;

    #[Assert\Currency]
    public ?string $currency = null;

    #[Assert\Choice(choices: self::LOCALES, message: 'Choose one of the languages offered.')]
    public ?string $locale = null;

    #[Assert\Timezone]
    public ?string $timezone = null;

    #[Assert\Range(min: 0, max: 1000)]
    public ?int $maxPublishedPages = null;

    #[Assert\Range(min: 0, max: 100)]
    public ?int $maxAssistants = null;

    #[Assert\Range(min: 0, max: 1000000)]
    public ?int $storageMb = null;

    #[Assert\Range(min: 1, max: 100)]
    public ?int $maxFileMb = null;

    /** @var list<string>|null AccountFeature values: the ones on */
    #[Assert\Choice(callback: [AccountFeature::class, 'values'], multiple: true, message: 'Choose among the features offered.')]
    public ?array $features = null;

    /** The locales Pontiac formats money and dates in. */
    public const LOCALES = ['es_CO', 'es_MX', 'es_PE', 'es_CL', 'es_ES', 'en_US'];
}
