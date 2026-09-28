<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use App\Entity\PlatformSettingsChange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformSettingsChange>
 */
class PlatformSettingsChangeRepository extends ServiceEntityRepository
{
    use ListQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformSettingsChange::class);
    }

    /**
     * Newest first; ?q= searches who changed it (name, email) and the names of the settings changed.
     */
    public function search(string $term, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.changedBy', 'u')
            ->addSelect('u')
            ->orderBy('c.changedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC');
        self::whereTerm($qb, $term, ['u.fullName', 'u.email', 'c.fields']);

        return self::paginate($qb, $pagination);
    }
}
