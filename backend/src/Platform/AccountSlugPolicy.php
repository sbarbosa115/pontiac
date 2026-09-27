<?php

declare(strict_types=1);

namespace App\Platform;

use App\Api\ApiValidationException;
use App\Entity\Account;
use App\Repository\AccountRepository;
use App\Repository\PlatformSettingsRepository;

/**
 * Whether a consultant can have this address (pontiac.co/<slug>): the right shape, not one of the app's own paths or
 * the extra reserved ones (Configuración › Legal), and nobody else's.
 */
final class AccountSlugPolicy
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly PlatformSettingsRepository $settings,
    ) {
    }

    /**
     * @param Account|null $for the consultant changing its own address, which it may keep
     *
     * @throws ApiValidationException on the "slug" field
     */
    public function assertAvailable(string $slug, ?Account $for = null): string
    {
        $slug = Account::normalizeSlug($slug);
        if (1 !== preg_match('/^'.Account::SLUG_PATTERN.'$/', $slug)) {
            throw ApiValidationException::single('slug', 'Use 3 to 60 lowercase letters, numbers and hyphens.');
        }
        if ($this->settings->current()->isReservedSlug($slug)) {
            throw ApiValidationException::single('slug', 'This address is reserved.');
        }
        $owner = $this->accounts->findOneBySlug($slug);
        if (null !== $owner && (null === $for || !$owner->getId()->equals($for->getId()))) {
            throw ApiValidationException::single('slug', 'This address is already taken.');
        }

        return $slug;
    }
}
