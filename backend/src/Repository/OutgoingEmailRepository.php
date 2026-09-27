<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use App\Entity\OutgoingEmail;
use App\Enum\EmailStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<OutgoingEmail>
 */
class OutgoingEmailRepository extends ServiceEntityRepository
{
    use ListQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OutgoingEmail::class);
    }

    /**
     * Newest first; ?q= searches the recipient, the subject and the consultant's name. An account id that is not a
     * UUID matches nothing.
     */
    public function search(string $term, ?EmailStatus $status, ?string $accountId, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.account', 'a')
            ->addSelect('a')
            ->orderBy('e.sentAt', 'DESC')
            ->addOrderBy('e.id', 'DESC');
        if (null !== $status) {
            $qb->andWhere('e.status = :status')->setParameter('status', $status);
        }
        if (null !== $accountId && '' !== $accountId) {
            if (!Uuid::isValid($accountId)) {
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere('e.account = :account')->setParameter('account', Uuid::fromString($accountId), UuidType::NAME);
            }
        }
        self::whereTerm($qb, $term, ['e.recipient', 'e.subject', 'a.name']);

        return self::paginate($qb, $pagination);
    }

    public function countFailedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.status = :failed')
            ->andWhere('e.sentAt >= :since')
            ->setParameter('failed', EmailStatus::Failed)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The emails a consultant's contact was sent (their Historial), the latest first.
     *
     * @return list<OutgoingEmail>
     */
    public function findForRecipient(\App\Entity\Account $account, string $email): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.account = :account')
            ->andWhere('e.recipient = :email')
            ->setParameter('account', $account->getId(), \Symfony\Bridge\Doctrine\Types\UuidType::NAME)
            ->setParameter('email', $email)
            ->orderBy('e.sentAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }
}
