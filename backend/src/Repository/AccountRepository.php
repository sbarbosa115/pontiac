<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use App\Entity\Account;
use App\Entity\User;
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

    /**
     * The consultants, newest first. ?q= searches the name, the address and the owner's email; $active narrows to
     * active (true) or suspended (false) ones.
     */
    public function search(string $term, ?bool $active, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('a')
            // The owner's is the only login of an account naming ROLE_OWNER; one per account, so no duplicates.
            ->leftJoin(User::class, 'o', 'WITH', "o.account = a AND o.roles LIKE '%ROLE_OWNER%'")
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');
        if (null !== $active) {
            $qb->andWhere('a.active = :active')->setParameter('active', $active);
        }
        self::whereTerm($qb, $term, ['a.name', 'a.slug', 'o.email']);

        return self::paginate($qb, $pagination);
    }

    /**
     * @return array{active: int, suspended: int}
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.active AS active, COUNT(a.id) AS total')
            ->groupBy('a.active')
            ->getQuery()
            ->getArrayResult();

        $counts = ['active' => 0, 'suspended' => 0];
        foreach ($rows as $row) {
            $counts[$row['active'] ? 'active' : 'suspended'] = (int) $row['total'];
        }

        return $counts;
    }
}
