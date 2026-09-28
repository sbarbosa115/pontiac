<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FlowRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A path the consultant draws for their people (Flujos): stages, and arrows between them that events follow. The
 * pages that feed it say so in their settings (`settings.flowId`).
 */
#[ORM\Entity(repositoryClass: FlowRepository::class)]
#[ORM\Index(name: 'idx_flow_account_name', columns: ['account_id', 'name'])]
class Flow implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, FlowStage> */
    #[ORM\OneToMany(targetEntity: FlowStage::class, mappedBy: 'flow', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $stages;

    /** @var Collection<int, FlowTransition> */
    #[ORM\OneToMany(targetEntity: FlowTransition::class, mappedBy: 'flow', cascade: ['persist'], orphanRemoval: true)]
    private Collection $transitions;

    public function __construct(Account $account, string $name)
    {
        $this->id = Uuid::v7();
        $this->setAccount($account);
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
        $this->stages = new ArrayCollection();
        $this->transitions = new ArrayCollection();
    }

    public function rename(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function addStage(FlowStage $stage): void
    {
        $this->stages->add($stage);
    }

    public function removeStage(FlowStage $stage): void
    {
        $this->stages->removeElement($stage);
    }

    public function addTransition(FlowTransition $transition): void
    {
        $this->transitions->add($transition);
    }

    public function clearTransitions(): void
    {
        $this->transitions->clear();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<FlowStage> */
    public function getStages(): array
    {
        return array_values($this->stages->toArray());
    }

    /** @return list<FlowTransition> */
    public function getTransitions(): array
    {
        return array_values($this->transitions->toArray());
    }

    public function startStage(): ?FlowStage
    {
        foreach ($this->stages as $stage) {
            if ($stage->isStart()) {
                return $stage;
            }
        }

        return null;
    }

    public function stage(Uuid $id): ?FlowStage
    {
        foreach ($this->stages as $stage) {
            if ($stage->getId()->equals($id)) {
                return $stage;
            }
        }

        return null;
    }
}
