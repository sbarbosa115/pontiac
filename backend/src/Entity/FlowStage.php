<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FlowStageKind;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** A stage of a flow: a column of the board, a box of the canvas. Entering it can send an email. */
#[ORM\Entity]
#[ORM\Index(name: 'idx_flow_stage_account_flow', columns: ['account_id', 'flow_id'])]
class FlowStage implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\ManyToOne(targetEntity: Flow::class, inversedBy: 'stages')]
    #[ORM\JoinColumn(nullable: false)]
    private Flow $flow;

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(length: 10, enumType: FlowStageKind::class)]
    private FlowStageKind $kind;

    // The board's column order and the canvas's position.
    #[ORM\Column(type: 'smallint')]
    private int $position;

    #[ORM\Column]
    private int $x;

    #[ORM\Column]
    private int $y;

    #[ORM\ManyToOne(targetEntity: EmailTemplate::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?EmailTemplate $emailTemplate = null;

    // Inicio flags people who stay longer than this.
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $alertDays = null;

    public function __construct(Flow $flow, ?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->setAccount($flow->getAccount() ?? throw new \LogicException('A flow belongs to an account.'));
        $this->flow = $flow;
        $this->name = '';
        $this->kind = FlowStageKind::Step;
        $this->position = 0;
        $this->x = 0;
        $this->y = 0;
    }

    public function change(string $name, FlowStageKind $kind, int $position, int $x, int $y, ?EmailTemplate $emailTemplate, ?int $alertDays): static
    {
        $this->name = $name;
        $this->kind = $kind;
        $this->position = $position;
        $this->x = $x;
        $this->y = $y;
        $this->emailTemplate = $emailTemplate;
        $this->alertDays = $alertDays;

        return $this;
    }

    public function getFlow(): Flow
    {
        return $this->flow;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKind(): FlowStageKind
    {
        return $this->kind;
    }

    public function isStart(): bool
    {
        return FlowStageKind::Start === $this->kind;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function getEmailTemplate(): ?EmailTemplate
    {
        return $this->emailTemplate;
    }

    public function getAlertDays(): ?int
    {
        return $this->alertDays;
    }
}
