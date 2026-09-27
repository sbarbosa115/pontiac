<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FlowTrigger;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** An arrow of a flow: people in `from` go to `to` when `trigger` happens to them. */
#[ORM\Entity]
#[ORM\Index(name: 'idx_flow_transition_account_flow', columns: ['account_id', 'flow_id'])]
class FlowTransition implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\ManyToOne(targetEntity: Flow::class, inversedBy: 'transitions')]
    #[ORM\JoinColumn(nullable: false)]
    private Flow $flow;

    #[ORM\ManyToOne(targetEntity: FlowStage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FlowStage $fromStage;

    #[ORM\ManyToOne(targetEntity: FlowStage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FlowStage $toStage;

    // "trigger" is a reserved word in MySQL.
    #[ORM\Column(name: 'trigger_event', length: 30, enumType: FlowTrigger::class)]
    private FlowTrigger $trigger;

    public function __construct(Flow $flow, FlowStage $from, FlowStage $to, FlowTrigger $trigger)
    {
        $this->id = Uuid::v7();
        $this->setAccount($flow->getAccount() ?? throw new \LogicException('A flow belongs to an account.'));
        $this->flow = $flow;
        $this->fromStage = $from;
        $this->toStage = $to;
        $this->trigger = $trigger;
    }

    public function getFromStage(): FlowStage
    {
        return $this->fromStage;
    }

    public function getToStage(): FlowStage
    {
        return $this->toStage;
    }

    public function getTrigger(): FlowTrigger
    {
        return $this->trigger;
    }
}
