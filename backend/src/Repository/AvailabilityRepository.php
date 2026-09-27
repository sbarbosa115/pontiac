<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Availability;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<Availability>
 */
class AvailabilityRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PlatformSettingsRepository $settings)
    {
        parent::__construct($registry, Availability::class);
    }

    /**
     * The account's availability; the first time, made from the platform's defaults (and saved on the next flush).
     */
    public function forAccount(Account $account): Availability
    {
        $availability = $this->createQueryBuilder('a')->getQuery()->getOneOrNullResult();
        if (null === $availability) {
            $availability = new Availability($account, $this->settings->current());
            $this->getEntityManager()->persist($availability);
        }

        return $availability;
    }
}
