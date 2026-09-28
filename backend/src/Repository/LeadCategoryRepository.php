<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use App\Entity\LeadCategory;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<LeadCategory>
 */
class LeadCategoryRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeadCategory::class);
    }

    /** By name; ?q= searches the name. */
    public function search(string $term, bool $includeInactive, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.name', 'ASC')->addOrderBy('c.id', 'ASC');
        if (!$includeInactive) {
            $qb->andWhere('c.active = true');
        }
        self::whereTerm($qb, $term, ['c.name']);

        return self::paginate($qb, $pagination);
    }

    /**
     * Every category, active ones first, by name: what the pickers offer (a disabled one stays visible where used).
     *
     * @return list<LeadCategory>
     */
    public function findAllForPickers(): array
    {
        return $this->createQueryBuilder('c')->orderBy('c.active', 'DESC')->addOrderBy('c.name', 'ASC')->getQuery()->getResult();
    }
}
