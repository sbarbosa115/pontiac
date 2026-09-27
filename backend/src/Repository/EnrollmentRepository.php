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
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
