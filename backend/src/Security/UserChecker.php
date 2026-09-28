<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\AccountFeature;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Runs on login and on every JWT-authenticated request, so disabling a user
 * or suspending an account takes effect immediately, not at token expiry.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Account disabled.');
        }

        if (null !== $user->getAccount() && !$user->getAccount()->isActive()) {
            throw new CustomUserMessageAccountStatusException('Account suspended.');
        }

        // A client exists for the portal: with the consultant's portal off, their sessions end at once too.
        if ($user->hasRole(User::ROLE_CLIENT) && true !== $user->getAccount()?->hasFeature(AccountFeature::Portal)) {
            throw new CustomUserMessageAccountStatusException('Client portal disabled.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
