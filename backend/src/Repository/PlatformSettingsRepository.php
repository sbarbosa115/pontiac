<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PlatformSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformSettings>
 */
class PlatformSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformSettings::class);
    }

    /**
     * The settings: the saved record, or the defaults until a super admin saves them (then persist it).
     */
    public function current(): PlatformSettings
    {
        return $this->findOneBy([]) ?? new PlatformSettings();
    }
}
