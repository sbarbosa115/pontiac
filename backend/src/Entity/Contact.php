<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContactStatus;
use App\Repository\ContactRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A person the consultant knows about: a prospecto who left their details on a page, later a client (PRD, "Contact").
 * One per email and consultant: every form the same person sends lands on the same contact (LeadSubmission).
 */
#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_contact_account_email', columns: ['account_id', 'email'])]
#[ORM\Index(name: 'idx_contact_account_activity', columns: ['account_id', 'last_activity_at'])]
class Contact implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\Column(length: 180)]
    private string $fullName;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone;

    #[ORM\Column(length: 10, enumType: ContactStatus::class)]
    private ContactStatus $status = ContactStatus::Lead;

    #[ORM\ManyToOne(targetEntity: LeadCategory::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?LeadCategory $category = null;

    // The page that brought them first.
    #[ORM\ManyToOne(targetEntity: LandingPage::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?LandingPage $sourcePage;

    // Ley 1581: when they accepted the privacy policy, and which text (a hash of it).
    #[ORM\Column]
    private \DateTimeImmutable $consentAt;

    #[ORM\Column(length: 64)]
    private string $consentPolicyHash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastActivityAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $anonymizedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $flowEmailsStoppedAt = null;

    public function __construct(Account $account, string $fullName, string $email, ?string $phone, ?LandingPage $sourcePage, string $consentPolicyHash)
    {
        $this->id = Uuid::v7();
        $this->setAccount($account);
        $this->fullName = $fullName;
        $this->email = self::normalizeEmail($email);
        $this->phone = $phone;
        $this->sourcePage = $sourcePage;
        $this->consentAt = new \DateTimeImmutable();
        $this->consentPolicyHash = $consentPolicyHash;
        $this->createdAt = $this->consentAt;
        $this->lastActivityAt = $this->consentAt;
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** They answered a form again: what they wrote last is what the consultant sees, and they consented again. */
    public function answeredAgain(string $fullName, ?string $phone, string $consentPolicyHash): static
    {
        $this->fullName = $fullName;
        $this->phone = $phone ?? $this->phone;
        $this->consentAt = new \DateTimeImmutable();
        $this->consentPolicyHash = $consentPolicyHash;
        $this->lastActivityAt = $this->consentAt;

        return $this;
    }

    /** They paid for a plan (a free one never does this): a client, again if they had finished. */
    public function becomeClient(): static
    {
        $this->status = ContactStatus::Client;
        $this->lastActivityAt = new \DateTimeImmutable();

        return $this;
    }

    /** "Finalizar asesoría": the consultancy ended; paying again makes them a client once more. */
    public function finish(): static
    {
        $this->status = ContactStatus::Finished;

        return $this;
    }

    /**
     * Ley 1581, on request: the person's data is erased, while the fact that someone answered stays (so counts and
     * history keep adding up). Irreversible.
     */
    public function anonymize(): static
    {
        $this->fullName = 'Anonimizado';
        $this->email = 'anonimizado-'.$this->id->toRfc4122().'@invalid';
        $this->phone = null;
        $this->anonymizedAt = new \DateTimeImmutable();

        return $this;
    }

    /** "No quiero recibir más correos": flows stop emailing them (confirmations and receipts still go). */
    public function stopFlowEmails(\DateTimeImmutable $now): static
    {
        $this->flowEmailsStoppedAt ??= $now;

        return $this;
    }

    public function getFlowEmailsStoppedAt(): ?\DateTimeImmutable
    {
        return $this->flowEmailsStoppedAt;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getStatus(): ContactStatus
    {
        return $this->status;
    }

    public function getCategory(): ?LeadCategory
    {
        return $this->category;
    }

    public function setCategory(?LeadCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getSourcePage(): ?LandingPage
    {
        return $this->sourcePage;
    }

    public function getConsentAt(): \DateTimeImmutable
    {
        return $this->consentAt;
    }

    public function getConsentPolicyHash(): string
    {
        return $this->consentPolicyHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastActivityAt(): \DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function getAnonymizedAt(): ?\DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    public function isAnonymized(): bool
    {
        return null !== $this->anonymizedAt;
    }
}
