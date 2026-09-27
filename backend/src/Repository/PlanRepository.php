<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use App\Entity\Plan;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<Plan>
 */
class PlanRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plan::class);
    }

    /** By name; ?q= searches name and description. */
    public function search(string $term, bool $includeInactive, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('p')->orderBy('p.name', 'ASC')->addOrderBy('p.id', 'ASC');
        if (!$includeInactive) {
            $qb->andWhere('p.active = true');
        }
        self::whereTerm($qb, $term, ['p.name', 'p.description']);

        return self::paginate($qb, $pagination);
    }

    /**
     * Every plan, active first, by name: what pickers offer.
     *
     * @return list<Plan>
     */
    public function findAllForPickers(): array
    {
        return $this->createQueryBuilder('p')->orderBy('p.active', 'DESC')->addOrderBy('p.name', 'ASC')->getQuery()->getResult();
    }
}
