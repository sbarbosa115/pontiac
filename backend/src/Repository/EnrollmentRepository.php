<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Enrollment;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends AccountOwnedRepository<Enrollment>
 */
class EnrollmentRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrollment::class);
    }

    /**
     * Newest first.
     *
     * @return list<Enrollment>
     */
    public function findForContact(Contact $contact): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.contact = :contact')
            ->setParameter('contact', $contact->getId(), UuidType::NAME)
            // Ids are time-ordered (UUIDv7): they settle what the seconds of createdAt cannot.
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByPaymentToken(string $token): ?Enrollment
    {
        return $this->createQueryBuilder('e')
            ->innerJoin('e.contact', 'c')
            ->addSelect('c')
            ->andWhere('e.paymentToken = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
