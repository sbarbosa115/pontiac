<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Flow;
use App\Api\Page;
use App\Api\Pagination;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<Flow>
 */
class FlowRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Flow::class);
    }

    /** By name; ?q= searches the name. */
    public function search(string $term, bool $includeInactive, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('f')->leftJoin('f.stages', 's')->addSelect('s')->orderBy('f.name', 'ASC')->addOrderBy('f.id', 'ASC');
        if (!$includeInactive) {
            $qb->andWhere('f.active = true');
        }
        self::whereTerm($qb, $term, ['f.name']);

        return self::paginate($qb, $pagination, fetchJoinCollection: true);
    }

    /**
     * Every flow with its stages, active first: pickers and the board.
     *
     * @return list<Flow>
     */
    public function findAllWithStages(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.stages', 's')
            ->addSelect('s')
            ->orderBy('f.active', 'DESC')
            ->addOrderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** A flow with its stages and arrows, loaded at once. */
    public function findOneWithGraph(string $id): ?Flow
    {
        $flow = $this->findOneById($id);
        if (null === $flow) {
            return null;
        }
        $this->getEntityManager()->createQueryBuilder()
            ->select('t', 'a', 'b')
            ->from(\App\Entity\FlowTransition::class, 't')
            ->innerJoin('t.fromStage', 'a')
            ->innerJoin('t.toStage', 'b')
            ->andWhere('t.flow = :flow')
            ->setParameter('flow', $flow->getId(), \Symfony\Bridge\Doctrine\Types\UuidType::NAME)
            ->getQuery()
            ->getResult();

        return $flow;
    }
}
