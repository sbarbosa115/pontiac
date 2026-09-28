<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use App\Entity\Contact;
use App\Enum\ContactStatus;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<Contact>
 */
class ContactRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * Most recent activity first; ?q= searches name, email and phone. Category and page are ids ("none" for a contact
     * without a category).
     */
    public function search(string $term, ?ContactStatus $status, ?string $categoryId, ?string $pageId, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.category', 'cat')
            ->addSelect('cat')
            ->leftJoin('c.sourcePage', 'p')
            ->addSelect('p')
            ->orderBy('c.lastActivityAt', 'DESC')
            ->addOrderBy('c.id', 'DESC');
        if (null !== $status) {
            $qb->andWhere('c.status = :status')->setParameter('status', $status);
        }
        if ('none' === $categoryId) {
            $qb->andWhere('c.category IS NULL');
        } else {
            self::whereId($qb, 'c.category', 'category', $categoryId);
        }
        self::whereId($qb, 'c.sourcePage', 'page', $pageId);
        self::whereTerm($qb, $term, ['c.fullName', 'c.email', 'c.phone']);

        return self::paginate($qb, $pagination);
    }

    public function findOneByEmail(string $email): ?Contact
    {
        return $this->findOneBy(['email' => Contact::normalizeEmail($email)]);
    }

    public function countCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
