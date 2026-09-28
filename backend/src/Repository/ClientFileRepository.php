<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ClientFile;
use App\Entity\Contact;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends AccountOwnedRepository<ClientFile>
 */
class ClientFileRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientFile::class);
    }

    /**
     * Newest first. For the client: active and shared only.
     *
     * @return list<ClientFile>
     */
    public function findForContact(Contact $contact, bool $forClient = false): array
    {
        $qb = $this->createQueryBuilder('f')
            ->innerJoin('f.uploadedBy', 'u')
            ->addSelect('u')
            ->andWhere('f.contact = :contact')
            ->setParameter('contact', $contact->getId(), UuidType::NAME)
            ->orderBy('f.createdAt', 'DESC')
            ->addOrderBy('f.id', 'DESC');
        if ($forClient) {
            $qb->andWhere('f.active = true')->andWhere('f.shared = true');
        }

        return $qb->getQuery()->getResult();
    }

    /** Bytes the account's files take, turned-off ones included (their files are kept): part of the storage limit. */
    public function totalBytes(): int
    {
        return (int) $this->createQueryBuilder('f')->select('COALESCE(SUM(f.sizeBytes), 0)')->getQuery()->getSingleScalarResult();
    }
}
