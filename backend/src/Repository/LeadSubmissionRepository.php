<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\LandingPage;
use App\Entity\LeadSubmission;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends AccountOwnedRepository<LeadSubmission>
 */
class LeadSubmissionRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeadSubmission::class);
    }

    /**
     * Newest first, with the page each came from.
     *
     * @return list<LeadSubmission>
     */
    public function findForContact(Contact $contact): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.page', 'p')
            ->addSelect('p')
            ->andWhere('s.contact = :contact')
            ->setParameter('contact', $contact->getId(), UuidType::NAME)
            ->orderBy('s.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * How many forms each of these pages received since then, keyed by page id (missing: none).
     *
     * @param list<LandingPage> $pages
     *
     * @return array<string, int>
     */
    public function countByPageSince(array $pages, \DateTimeImmutable $since): array
    {
        if ([] === $pages) {
            return [];
        }

        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.page) AS page, COUNT(s.id) AS total')
            ->andWhere('s.page IN (:pages)')
            ->andWhere('s.submittedAt >= :since')
            ->setParameter('pages', self::ids($pages), ArrayParameterType::BINARY)
            ->setParameter('since', $since)
            ->groupBy('s.page')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[Uuid::fromBinary((string) $row['page'])->toRfc4122()] = (int) $row['total'];
        }

        return $counts;
    }
}
