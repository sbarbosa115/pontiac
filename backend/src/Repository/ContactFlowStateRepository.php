<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactFlowState;
use App\Entity\Contact;
use App\Entity\Flow;
use App\Entity\FlowStage;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<ContactFlowState>
 */
class ContactFlowStateRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactFlowState::class);
    }

    /**
     * A person's places in every flow, with the flow and stage.
     *
     * @return list<ContactFlowState>
     */
    public function findForContact(Contact $contact): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.flow', 'f')
            ->addSelect('f')
            ->innerJoin('c.stage', 's')
            ->addSelect('s')
            ->andWhere('c.contact = :contact')
            ->setParameter('contact', $contact->getId(), UuidType::NAME)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneFor(Contact $contact, Flow $flow): ?ContactFlowState
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.contact = :contact')
            ->andWhere('c.flow = :flow')
            ->setParameter('contact', $contact->getId(), UuidType::NAME)
            ->setParameter('flow', $flow->getId(), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Everyone in a flow, with their contact and category, the longest waiting first: the board.
     *
     * @return list<ContactFlowState>
     */
    public function findForFlow(Flow $flow): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.contact', 'p')
            ->addSelect('p')
            ->leftJoin('p.category', 'k')
            ->addSelect('k')
            ->andWhere('c.flow = :flow')
            ->setParameter('flow', $flow->getId(), UuidType::NAME)
            ->orderBy('c.enteredAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<string, int> people per flow id */
    public function countByFlow(): array
    {
        $rows = $this->createQueryBuilder('c')->select('IDENTITY(c.flow) AS flow', 'COUNT(c.id) AS n')->groupBy('c.flow')->getQuery()->getArrayResult();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) Uuid::fromBinary($row['flow'])] = (int) $row['n'];
        }

        return $counts;
    }

    public function countInStage(FlowStage $stage): int
    {
        return (int) $this->createQueryBuilder('c')->select('COUNT(c.id)')->andWhere('c.stage = :stage')
            ->setParameter('stage', $stage->getId(), UuidType::NAME)->getQuery()->getSingleScalarResult();
    }

    /**
     * People who stayed in a stage longer than its alert, the longest first.
     *
     * @return list<ContactFlowState>
     */
    public function findOverdue(\DateTimeImmutable $now, int $limit = 10): array
    {
        $states = $this->createQueryBuilder('c')
            ->innerJoin('c.contact', 'p')
            ->addSelect('p')
            ->innerJoin('c.flow', 'f')
            ->addSelect('f')
            ->innerJoin('c.stage', 's')
            ->addSelect('s')
            ->andWhere('s.alertDays IS NOT NULL')
            ->andWhere('f.active = true')
            ->orderBy('c.enteredAt', 'ASC')
            ->getQuery()
            ->getResult();

        return \array_slice(array_values(array_filter($states, static fn (ContactFlowState $s) => $s->getEnteredAt() < $now->modify(sprintf('-%d days', (int) $s->getStage()->getAlertDays())))), 0, $limit);
    }
}
