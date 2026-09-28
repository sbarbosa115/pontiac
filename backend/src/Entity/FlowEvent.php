<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FlowEventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One move of a person in a flow (Historial): from where to where, why (an event, `manual`, `added`, `removed`) and by
 * whom when a person did it. Names are copied: the history reads the same after the flow changes.
 */
#[ORM\Entity(repositoryClass: FlowEventRepository::class)]
#[ORM\Index(name: 'idx_flow_event_account_contact', columns: ['account_id', 'contact_id', 'created_at'])]
class FlowEvent implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    public const ADDED = 'added';
    public const REMOVED = 'removed';
    public const MANUAL = 'manual';

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Contact $contact;

    #[ORM\ManyToOne(targetEntity: Flow::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Flow $flow;

    #[ORM\Column(length: 120)]
    private string $flowName;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $fromStage;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $toStage;

    // A FlowTrigger value, or added / removed / manual.
    #[ORM\Column(length: 30)]
    private string $reason;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $byUser;

    // The stage email this move sent, if any.
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $emailSubject = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Contact $contact, Flow $flow, ?FlowStage $from, ?FlowStage $to, string $reason, ?User $byUser, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->setAccount($contact->getAccount() ?? throw new \LogicException('A contact belongs to an account.'));
        $this->contact = $contact;
        $this->flow = $flow;
        $this->flowName = $flow->getName();
        $this->fromStage = $from?->getName();
        $this->toStage = $to?->getName();
        $this->reason = $reason;
        $this->byUser = $byUser;
        $this->createdAt = $now;
    }

    public function emailed(string $subject): static
    {
        $this->emailSubject = $subject;

        return $this;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function getFlowName(): string
    {
        return $this->flowName;
    }

    public function getFromStage(): ?string
    {
        return $this->fromStage;
    }

    public function getToStage(): ?string
    {
        return $this->toStage;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getByUser(): ?User
    {
        return $this->byUser;
    }

    public function getEmailSubject(): ?string
    {
        return $this->emailSubject;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
