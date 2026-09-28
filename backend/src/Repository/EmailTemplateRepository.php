<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailTemplate;
use App\Api\Page;
use App\Api\Pagination;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<EmailTemplate>
 */
class EmailTemplateRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplate::class);
    }

    /** By name; ?q= searches the name and subject. */
    public function search(string $term, bool $includeInactive, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('t')->orderBy('t.name', 'ASC')->addOrderBy('t.id', 'ASC');
        if (!$includeInactive) {
            $qb->andWhere('t.active = true');
        }
        self::whereTerm($qb, $term, ['t.name', 't.subject']);

        return self::paginate($qb, $pagination);
    }

    /**
     * @return list<EmailTemplate>
     */
    public function findAllForPickers(): array
    {
        return $this->createQueryBuilder('t')->orderBy('t.active', 'DESC')->addOrderBy('t.name', 'ASC')->getQuery()->getResult();
    }
}
