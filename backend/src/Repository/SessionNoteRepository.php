<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BookingSession;
use App\Entity\SessionNote;
use App\Enum\NoteVisibility;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends AccountOwnedRepository<SessionNote>
 */
class SessionNoteRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SessionNote::class);
    }

    /**
     * Oldest first; with $sharedOnly, what an assistant (or a client) may read.
     *
     * @return list<SessionNote>
     */
    public function findForSession(BookingSession $session, bool $sharedOnly): array
    {
        $qb = $this->createQueryBuilder('n')
            ->innerJoin('n.author', 'a')
            ->addSelect('a')
            ->andWhere('n.session = :session')
            ->setParameter('session', $session->getId(), UuidType::NAME)
            ->orderBy('n.createdAt', 'ASC');
        if ($sharedOnly) {
            $qb->andWhere('n.visibility = :shared')->setParameter('shared', NoteVisibility::Shared);
        }

        return $qb->getQuery()->getResult();
    }
}
