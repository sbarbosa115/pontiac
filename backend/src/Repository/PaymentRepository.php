<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use App\Entity\Contact;
use App\Entity\Payment;
use App\Enum\PaymentStatus;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends AccountOwnedRepository<Payment>
 */
class PaymentRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /** Newest first; ?q= searches the person's name and email and the reference; $to is exclusive. */
    public function search(string $term, ?PaymentStatus $status, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, Pagination $pagination): Page
    {
        $qb = $this->withRelations()->orderBy('p.createdAt', 'DESC')->addOrderBy('p.id', 'DESC');
        if (null !== $status) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }
        if (null !== $from) {
            $qb->andWhere('p.createdAt >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $qb->andWhere('p.createdAt < :to')->setParameter('to', $to);
        }
        self::whereTerm($qb, $term, ['c.fullName', 'c.email', 'p.reference']);

        return self::paginate($qb, $pagination);
    }

    public function findOneByReference(string $reference): ?Payment
    {
        return $this->withRelations()->andWhere('p.reference = :reference')->setParameter('reference', $reference)->getQuery()->getOneOrNullResult();
    }

    /**
     * Newest first.
     *
     * @return list<Payment>
     */
    public function findForContact(Contact $contact): array
    {
        return $this->withRelations()
            ->andWhere('p.contact = :contact')
            ->setParameter('contact', $contact->getId(), UuidType::NAME)
            // Ids are time-ordered (UUIDv7): they settle what the seconds of createdAt cannot.
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Approved payments since then: how many and how much (a decimal string, summed by the database).
     *
     * @return array{count: int, total: string}
     */
    public function approvedSince(\DateTimeImmutable $since): array
    {
        /** @var array{count: int|string, total: string|null} $row */
        $row = $this->createQueryBuilder('p')
            ->select('COUNT(p.id) AS count', 'SUM(p.amount) AS total')
            ->andWhere('p.status = :approved')
            ->andWhere('p.paidAt >= :since')
            ->setParameter('approved', PaymentStatus::Approved)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleResult();

        return ['count' => (int) $row['count'], 'total' => $row['total'] ?? '0.00'];
    }

    private function withRelations(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.contact', 'c')
            ->addSelect('c')
            ->innerJoin('p.enrollment', 'e')
            ->addSelect('e')
            ->leftJoin('p.recordedBy', 'u')
            ->addSelect('u');
    }
}
