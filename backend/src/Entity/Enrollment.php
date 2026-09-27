<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EnrollmentStatus;
use App\Repository\EnrollmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One contact taking one plan once: its sessions and how many are used. The plan's name, price, sessions and length
 * are copied in, so changing the plan later does not change what this person signed up for.
 */
#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
#[ORM\Index(name: 'idx_enrollment_account_contact', columns: ['account_id', 'contact_id'])]
class Enrollment implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Contact $contact;

    #[ORM\ManyToOne(targetEntity: Plan::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Plan $plan;

    #[ORM\Column(length: 120)]
    private string $planName;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $price;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $sessionsIncluded;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $durationMinutes;

    #[ORM\Column(length: 20, enumType: EnrollmentStatus::class)]
    private EnrollmentStatus $status;

    #[ORM\ManyToOne(targetEntity: LandingPage::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?LandingPage $sourcePage;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Contact $contact, Plan $plan, ?LandingPage $sourcePage)
    {
        $this->id = Uuid::v7();
        $this->setAccount($contact->getAccount() ?? throw new \LogicException('A contact belongs to an account.'));
        $this->contact = $contact;
        $this->plan = $plan;
        $this->planName = $plan->getName();
        $this->price = $plan->getPrice();
        $this->currency = $plan->getCurrency();
        $this->sessionsIncluded = $plan->getSessions();
        $this->durationMinutes = $plan->getDurationMinutes();
        // A free plan starts at once; a paid one waits for its payment (milestone 3).
        $this->status = $plan->isFree() ? EnrollmentStatus::Active : EnrollmentStatus::PendingPayment;
        $this->sourcePage = $sourcePage;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function getPlan(): Plan
    {
        return $this->plan;
    }

    public function getPlanName(): string
    {
        return $this->planName;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getSessionsIncluded(): int
    {
        return $this->sessionsIncluded;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function getStatus(): EnrollmentStatus
    {
        return $this->status;
    }

    public function getSourcePage(): ?LandingPage
    {
        return $this->sourcePage;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
