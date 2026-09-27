<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides the switch_user attempts on the api firewall: only an active ROLE_SUPER_ADMIN may act as
 * someone else, and only as an active owner or assistant of an active account (User::canBeImpersonated).
 *
 * UserChecker::checkPreAuth (active user, active account) does not run on the switched-to user, so those
 * checks live here too. This runs before the account filter is configured, so it must not load account-owned
 * entities.
 *
 * @extends Voter<string, User>
 */
final class ImpersonationVoter extends Voter
{
    public const ATTRIBUTE = 'IMPERSONATE_USER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $impersonator = $token->getUser();

        return $impersonator instanceof User
            && $impersonator->hasRole(User::ROLE_SUPER_ADMIN)
            && $impersonator->isActive()
            && $subject->canBeImpersonated();
    }
}
