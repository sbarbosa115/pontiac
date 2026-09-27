<?php

declare(strict_types=1);

namespace App\Platform;

use App\Entity\Account;
use App\Repository\PlatformSettingsRepository;
use App\Security\InvitationService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A new consultant: the account, with the platform's default limits and features copied in (so a later change of the
 * defaults leaves it alone), and its owner, invited by email.
 */
final class AccountCreator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlatformSettingsRepository $settings,
        private readonly AccountSlugPolicy $slugs,
        private readonly InvitationService $invitations,
    ) {
    }

    public function create(string $name, string $slug, string $ownerName, string $ownerEmail): Account
    {
        $slug = $this->slugs->assertAvailable($slug);
        // Before anything is saved: a taken email must not leave a consultant without an owner behind.
        $this->invitations->assertStaffEmailAvailable($ownerEmail);

        $defaults = $this->settings->current();
        $account = (new Account(trim($name), $slug))
            ->setFeatures($defaults->getDefaultFeatures())
            ->setLimits($defaults->getDefaultMaxPublishedPages(), $defaults->getDefaultMaxAssistants(), $defaults->getDefaultStorageMb(), $defaults->getDefaultMaxFileMb());
        $this->em->persist($account);
        // Saves both, then emails the owner.
        $this->invitations->inviteOwner($account, $ownerEmail, trim($ownerName));

        return $account;
    }
}
