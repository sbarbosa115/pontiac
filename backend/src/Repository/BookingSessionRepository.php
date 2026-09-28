<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use App\Entity\BookingSession;
use App\Entity\Contact;
use App\Entity\Enrollment;
use App\Enum\SessionStatus;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends AccountOwnedRepository<BookingSession>
 */
class BookingSessionRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingSession::class);
    }

    /**
     * By time (the soonest first); ?q= searches the contact's name and email; dates bound the start (UTC).
     */
    public function search(string $term, ?SessionStatus $status, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, Pagination $pagination): Page
    {
        $qb = $this->withRelations()->orderBy('s.startsAt', 'ASC')->addOrderBy('s.id', 'ASC');
        if (null !== $status) {
            $qb->andWhere('s.status = :status')->setParameter('status', $status);
        }
        if (null !== $from) {
            $qb->andWhere('s.startsAt >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $qb->andWhere('s.startsAt < :to')->setParameter('to', $to);
        }
        self::whereTerm($qb, $term, ['c.fullName', 'c.email']);

        return self::paginate($qb, $pagination);
    }

    /**
     * The scheduled sessions that start in [from, to): what the week view and the slot finder see.
     *
     * @return list<BookingSession>
     */
    public function findScheduledBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->withRelations()
            ->andWhere('s.status = :scheduled')
            ->andWhere('s.startsAt < :to')
            ->andWhere('s.endsAt > :from')
            ->setParameter('scheduled', SessionStatus::Scheduled)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('s.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Whether a scheduled session (other than $except) comes within $bufferMinutes of [starts, ends).
     */
    public function overlaps(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, int $bufferMinutes, ?BookingSession $except = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.status = :scheduled')
            ->andWhere('s.startsAt < :to')
            ->andWhere('s.endsAt > :from')
            ->setParameter('scheduled', SessionStatus::Scheduled)
            ->setParameter('from', $startsAt->modify(sprintf('-%d minutes', $bufferMinutes)))
            ->setParameter('to', $endsAt->modify(sprintf('+%d minutes', $bufferMinutes)));
        if (null !== $except) {
            $qb->andWhere('s.id <> :except')->setParameter('except', $except->getId(), UuidType::NAME);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findOneByManageToken(string $token): ?BookingSession
    {
        return $this->withRelations()
            ->andWhere('s.manageTokenHash = :hash')
            ->setParameter('hash', BookingSession::hashToken($token))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The scheduled sessions whose <hours>-hour reminder is due now: starting within that many hours, not started, and
     * last scheduled before the reminder's time (a session booked an hour ahead gets no 24-hour reminder).
     *
     * @return list<BookingSession>
     */
    public function findDueForReminder(int $hours, \DateTimeImmutable $now): array
    {
        $sessions = $this->withRelations()
            ->andWhere('s.status = :scheduled')
            ->andWhere('s.startsAt > :now')
            ->andWhere('s.startsAt <= :horizon')
            ->setParameter('scheduled', SessionStatus::Scheduled)
            ->setParameter('now', $now)
            ->setParameter('horizon', $now->modify(sprintf('+%d hours', $hours)))
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $sessions,
            static fn (BookingSession $s) => $s->getScheduledAt() < $s->getStartsAt()->modify(sprintf('-%d hours', $hours)),
        ));
    }

    /**
     * Newest first.
     *
     * @return list<BookingSession>
     */
    public function findForContact(Contact $contact): array
    {
        return $this->withRelations()
            ->andWhere('s.contact = :contact')
            ->setParameter('contact', $contact->getId(), UuidType::NAME)
            ->orderBy('s.startsAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function withRelations(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.contact', 'c')
            ->addSelect('c')
            ->innerJoin('s.enrollment', 'e')
            ->addSelect('e');
    }

    /**
     * Per enrollment: sessions taken (scheduled, done or no-show) and used (done or no-show). One query for all.
     *
     * @param list<Enrollment> $enrollments
     *
     * @return array<string, array{taken: int, used: int}> by enrollment id
     */
    public function countsByEnrollment(array $enrollments): array
    {
        if ([] === $enrollments) {
            return [];
        }
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.enrollment) AS enrollment', 's.status AS status', 'COUNT(s.id) AS n')
            ->andWhere('s.enrollment IN (:enrollments)')
            ->andWhere('s.status != :cancelled')
            ->setParameter('enrollments', array_map(static fn (Enrollment $e) => $e->getId()->toBinary(), $enrollments))
            ->setParameter('cancelled', SessionStatus::Cancelled)
            ->groupBy('s.enrollment', 's.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($enrollments as $enrollment) {
            $counts[(string) $enrollment->getId()] = ['taken' => 0, 'used' => 0];
        }
        foreach ($rows as $row) {
            $id = (string) Uuid::fromBinary($row['enrollment']);
            $status = $row['status'] instanceof SessionStatus ? $row['status'] : SessionStatus::from((string) $row['status']);
            $counts[$id]['taken'] += (int) $row['n'];
            if (SessionStatus::Scheduled !== $status) {
                $counts[$id]['used'] += (int) $row['n'];
            }
        }

        return $counts;
    }
}

