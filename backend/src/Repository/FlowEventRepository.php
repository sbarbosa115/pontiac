<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FlowEvent;
use App\Entity\Contact;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<FlowEvent>
 */
class FlowEventRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlowEvent::class);
    }

    /**
     * A person's history in flows, the latest first.
     *
     * @return list<FlowEvent>
     */
    public function findForContact(Contact $contact): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.byUser', 'u')
            ->addSelect('u')
            ->andWhere('e.contact = :contact')
            ->setParameter('contact', $contact->getId(), UuidType::NAME)
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
