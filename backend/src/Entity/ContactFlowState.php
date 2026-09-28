<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContactFlowStateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Where a person is in a flow now: one stage per flow, and since when. */
#[ORM\Entity(repositoryClass: ContactFlowStateRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_contact_flow', columns: ['contact_id', 'flow_id'])]
#[ORM\Index(name: 'idx_contact_flow_account_stage', columns: ['account_id', 'stage_id'])]
class ContactFlowState implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Contact $contact;

    #[ORM\ManyToOne(targetEntity: Flow::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Flow $flow;

    #[ORM\ManyToOne(targetEntity: FlowStage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private FlowStage $stage;

    #[ORM\Column]
    private \DateTimeImmutable $enteredAt;

    public function __construct(Contact $contact, FlowStage $stage, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->setAccount($contact->getAccount() ?? throw new \LogicException('A contact belongs to an account.'));
        $this->contact = $contact;
        $this->flow = $stage->getFlow();
        $this->stage = $stage;
        $this->enteredAt = $now;
    }

    public function moveTo(FlowStage $stage, \DateTimeImmutable $now): static
    {
        $this->stage = $stage;
        $this->enteredAt = $now;

        return $this;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function getFlow(): Flow
    {
        return $this->flow;
    }

    public function getStage(): FlowStage
    {
        return $this->stage;
    }

    public function getEnteredAt(): \DateTimeImmutable
    {
        return $this->enteredAt;
    }
}
