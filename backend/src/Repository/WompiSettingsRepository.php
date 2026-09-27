<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\WompiSettings;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<WompiSettings>
 */
class WompiSettingsRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WompiSettings::class);
    }

    /** The entered account's settings, if it ever saved some. */
    public function current(): ?WompiSettings
    {
        return $this->createQueryBuilder('w')->getQuery()->getOneOrNullResult();
    }

    /** The account's settings; the first time, an empty row (saved on the next flush). */
    public function forAccount(Account $account): WompiSettings
    {
        $settings = $this->current();
        if (null === $settings) {
            $settings = new WompiSettings($account);
            $this->getEntityManager()->persist($settings);
        }

        return $settings;
    }
}
