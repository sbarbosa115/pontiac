<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentStatus;
use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One attempt to pay for a plan: through Wompi (our reference, their transaction) or recorded by the owner (cash,
 * transfer). Only a pending payment changes; its result comes from Wompi, checked against what we asked for.
 */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Index(name: 'idx_payment_account_created', columns: ['account_id', 'created_at'])]
#[ORM\Index(name: 'idx_payment_account_status', columns: ['account_id', 'status'])]
class Payment implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\ManyToOne(targetEntity: Enrollment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Enrollment $enrollment;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Contact $contact;

    // Ours, sent to Wompi and back in every event: "PON-…".
    #[ORM\Column(length: 40, unique: true)]
    private string $reference;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 10, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::Pending;

    // Wompi's payment method type (CARD, PSE, NEQUI, …) or a manual one (cash, transfer, other).
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $wompiTransactionId = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $lastEvent = null;

    #[ORM\Column]
    private bool $manual = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $recordedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    private function __construct(Enrollment $enrollment)
    {
        $this->id = Uuid::v7();
        $this->setAccount($enrollment->getAccount() ?? throw new \LogicException('An enrollment belongs to an account.'));
        $this->enrollment = $enrollment;
        $this->contact = $enrollment->getContact();
        $this->amount = $enrollment->getPrice();
        $this->currency = $enrollment->getCurrency();
        $this->reference = 'PON-'.strtoupper(bin2hex(random_bytes(8)));
        $this->createdAt = new \DateTimeImmutable();
    }

    /** A checkout at Wompi, waiting for its result. */
    public static function forCheckout(Enrollment $enrollment): self
    {
        return new self($enrollment);
    }

    /** Money the owner received outside Wompi: approved as it is recorded. */
    public static function manual(Enrollment $enrollment, string $method, ?string $note, User $recordedBy, \DateTimeImmutable $paidAt): self
    {
        $payment = new self($enrollment);
        $payment->manual = true;
        $payment->method = $method;
        $payment->note = $note;
        $payment->recordedBy = $recordedBy;
        $payment->status = PaymentStatus::Approved;
        $payment->paidAt = $paidAt;

        return $payment;
    }

    /**
     * Wompi's result for this payment.
     *
     * @param array<string, mixed> $transaction
     */
    public function settle(PaymentStatus $status, string $transactionId, ?string $method, array $transaction, \DateTimeImmutable $now): static
    {
        if (PaymentStatus::Pending !== $this->status) {
            throw new \LogicException('Only a pending payment changes.');
        }
        $this->status = $status;
        $this->wompiTransactionId = $transactionId;
        $this->method = $method;
        $this->lastEvent = $transaction;
        if (PaymentStatus::Approved === $status) {
            $this->paidAt = $now;
        }

        return $this;
    }

    /** Wompi knows the transaction but has no result yet: remember it, so the result page can ask again. */
    public function track(string $transactionId): static
    {
        $this->wompiTransactionId ??= $transactionId;

        return $this;
    }

    /** Amount in cents, as Wompi counts it: "250000.00" → 25000000, without floats. */
    public function getAmountInCents(): int
    {
        [$units, $cents] = explode('.', $this->amount.'.00');

        return (int) $units * 100 + (int) str_pad(substr($cents, 0, 2), 2, '0');
    }

    public function getEnrollment(): Enrollment
    {
        return $this->enrollment;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getWompiTransactionId(): ?string
    {
        return $this->wompiTransactionId;
    }

    public function isManual(): bool
    {
        return $this->manual;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getRecordedBy(): ?User
    {
        return $this->recordedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }
}
