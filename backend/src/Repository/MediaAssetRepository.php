<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use App\Entity\MediaAsset;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<MediaAsset>
 */
class MediaAssetRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaAsset::class);
    }

    /** Newest first; ?q= searches the file name and the alt text. */
    public function search(string $term, bool $includeInactive, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('m')->orderBy('m.createdAt', 'DESC')->addOrderBy('m.id', 'DESC');
        if (!$includeInactive) {
            $qb->andWhere('m.active = true');
        }
        self::whereTerm($qb, $term, ['m.originalName', 'm.altText']);

        return self::paginate($qb, $pagination);
    }

    /** Bytes the account's images take, disabled ones included (their files are kept): what the storage limit counts. */
    public function totalBytes(): int
    {
        return (int) $this->createQueryBuilder('m')->select('COALESCE(SUM(m.totalBytes), 0)')->getQuery()->getSingleScalarResult();
    }

    /**
     * The active images among these ids, keyed by id: what a page may show.
     *
     * @param list<string> $ids
     *
     * @return array<string, MediaAsset>
     */
    public function findActiveByIds(array $ids): array
    {
        $found = [];
        foreach ($this->findByIds($ids) as $asset) {
            if ($asset->isActive()) {
                $found[(string) $asset->getId()] = $asset;
            }
        }

        return $found;
    }
}
