<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EnrollmentOutcome;
use App\Enum\EnrollmentStatus;
use App\Repository\EnrollmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One contact taking one plan once: its sessions and how many are used. The plan's name, price, sessions and length
 * are copied in, so changing the plan later does not change what this person signed up for.
 *
 * pending_payment → active (paid, or free) → completed (its sessions used) → renewed or finished (`outcome`);
 * a plan waiting for payment can be cancelled. A paid plan carries the token of its payment link.
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

    // The payment link's secret part (/<consultant>/pagar/<token>): random, only lets someone pay this plan.
    #[ORM\Column(length: 43, unique: true, nullable: true)]
    private ?string $paymentToken = null;

    #[ORM\Column(length: 10, nullable: true, enumType: EnrollmentOutcome::class)]
    private ?EnrollmentOutcome $outcome = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

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
        if (!$plan->isFree()) {
            $this->paymentToken = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        }
    }

    /** Paid (or paid again after it was cancelled: the money came in). */
    public function activate(): static
    {
        if (\in_array($this->status, [EnrollmentStatus::PendingPayment, EnrollmentStatus::Cancelled], true)) {
            $this->status = EnrollmentStatus::Active;
        }

        return $this;
    }

    public function cancel(): static
    {
        if (EnrollmentStatus::PendingPayment !== $this->status) {
            throw new \DomainException('Only a plan waiting for payment is cancelled.');
        }
        $this->status = EnrollmentStatus::Cancelled;

        return $this;
    }

    /**
     * Every session used (done or no-show); a session reopened by mistake makes it active again.
     *
     * @return bool whether this completed it
     */
    public function progress(int $sessionsUsed, \DateTimeImmutable $now): bool
    {
        if (EnrollmentStatus::Active === $this->status && $sessionsUsed >= $this->sessionsIncluded) {
            $this->status = EnrollmentStatus::Completed;
            $this->completedAt = $now;

            return true;
        }
        if (EnrollmentStatus::Completed === $this->status && $sessionsUsed < $this->sessionsIncluded && null === $this->outcome) {
            $this->status = EnrollmentStatus::Active;
            $this->completedAt = null;
        }

        return false;
    }

    public function conclude(EnrollmentOutcome $outcome): static
    {
        if (EnrollmentStatus::Completed !== $this->status || null !== $this->outcome) {
            throw new \DomainException('Only a completed plan without an outcome is renewed or finished.');
        }
        $this->outcome = $outcome;

        return $this;
    }

    public function isFree(): bool
    {
        return '0.00' === $this->price || 1 === preg_match('/^0+(\.0+)?$/', $this->price);
    }

    public function getPaymentToken(): ?string
    {
        return $this->paymentToken;
    }

    public function getOutcome(): ?EnrollmentOutcome
    {
        return $this->outcome;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
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
