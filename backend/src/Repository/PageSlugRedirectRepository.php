<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PageSlugRedirect;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<PageSlugRedirect>
 */
class PageSlugRedirectRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageSlugRedirect::class);
    }

    public function findOneByOldSlug(string $slug): ?PageSlugRedirect
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.page', 'p')
            ->addSelect('p')
            ->andWhere('r.oldSlug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
