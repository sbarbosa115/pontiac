<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Accounts are not account-owned (the filter does not apply): an account is looked up by its slug on the public
 * routes before anyone is signed in, and listed by the super admin.
 *
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    use ListQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findOneById(string $id): ?Account
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.id = :id')
            ->setParameter('id', Uuid::fromString($id), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneBySlug(string $slug): ?Account
    {
        return $this->findOneBy(['slug' => Account::normalizeSlug($slug)]);
    }

    /**
     * The account whose public pages and portal answer at /<slug>: an active one, or none.
     */
    public function findActiveBySlug(string $slug): ?Account
    {
        return $this->findOneBy(['slug' => Account::normalizeSlug($slug), 'active' => true]);
    }
}
