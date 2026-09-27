<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Output\ImpersonatableAccountOutput;
use App\Api\Output\ImpersonatableUserOutput;
use App\Api\Output\MeAccountOutput;
use App\Api\Output\TeamMemberOutput;
use App\Entity\Account;
use App\Entity\User;

/**
 * Entities → Output DTOs, one place per shape, so every endpoint that shows the same thing shows it the same way.
 * A Presenter method reads only what the repository fetched (no N+1: ListQueryCountTest).
 */
final class Presenter
{
    public static function meAccount(Account $account): MeAccountOutput
    {
        return new MeAccountOutput(
            id: (string) $account->getId(),
            name: $account->getName(),
            slug: $account->getSlug(),
            country: $account->getCountry(),
            currency: $account->getCurrency(),
            locale: $account->getLocale(),
            timezone: $account->getTimezone(),
        );
    }

    public static function teamMember(User $user): TeamMemberOutput
    {
        return new TeamMemberOutput(
            id: (string) $user->getId(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            role: $user->hasRole(User::ROLE_OWNER) ? 'owner' : 'assistant',
            active: $user->isActive(),
            loginStatus: $user->getLoginStatus(),
            lastSignInAt: self::timestamp($user->getLastSignInAt()),
        );
    }

    public static function impersonatableUser(User $user): ImpersonatableUserOutput
    {
        $account = $user->getAccount() ?? throw new \LogicException('Only a user with an account can be impersonated.');

        return new ImpersonatableUserOutput(
            id: (string) $user->getId(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            role: $user->hasRole(User::ROLE_OWNER) ? 'owner' : 'assistant',
            account: new ImpersonatableAccountOutput(id: (string) $account->getId(), name: $account->getName()),
        );
    }

    /** ISO 8601 in UTC, the API's one timestamp format. */
    public static function timestamp(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM);
    }
}
