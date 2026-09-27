<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccountSlugRedirect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountSlugRedirect>
 */
class AccountSlugRedirectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountSlugRedirect::class);
    }

    public function findOneByOldSlug(string $slug): ?AccountSlugRedirect
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.account', 'a')
            ->addSelect('a')
            ->andWhere('r.oldSlug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
