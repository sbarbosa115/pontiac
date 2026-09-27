<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EmailStatus;
use App\Repository\OutgoingEmailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One attempt to send an email, and how it ended (Correos). Written by App\Mail\EmailLog as the mailer sends, straight
 * through DBAL, so logging never flushes someone else's pending changes.
 *
 * Not account-owned: the platform's own emails (a consultant's invitation, a super admin's) belong to nobody, and only
 * the super admin reads the log. $account says which consultant an email was sent for, when it was.
 */
#[ORM\Entity(repositoryClass: OutgoingEmailRepository::class)]
#[ORM\Index(name: 'idx_email_sent_at', columns: ['sent_at'])]
#[ORM\Index(name: 'idx_email_account_sent_at', columns: ['account_id', 'sent_at'])]
#[ORM\Index(name: 'idx_email_status_sent_at', columns: ['status', 'sent_at'])]
class OutgoingEmail
{
    use HasUuid;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Account $account = null;

    // What the email is (invitation, test, reminder…): App\Mail\EmailTag.
    #[ORM\Column(length: 40)]
    private string $kind;

    #[ORM\Column(length: 255)]
    private string $recipient;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(length: 10, enumType: EmailStatus::class)]
    private EmailStatus $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column]
    private \DateTimeImmutable $sentAt;

    public function __construct(?Account $account, string $kind, string $recipient, string $subject, EmailStatus $status, ?string $error)
    {
        $this->id = Uuid::v7();
        $this->account = $account;
        $this->kind = mb_substr($kind, 0, 40);
        $this->recipient = mb_substr($recipient, 0, 255);
        $this->subject = mb_substr($subject, 0, 255);
        $this->status = $status;
        $this->error = null === $error ? null : mb_substr($error, 0, 2000);
        $this->sentAt = new \DateTimeImmutable();
    }

    /**
     * The row as outgoing_email stores it, for EmailLog's DBAL insert (the column names Doctrine maps these to).
     *
     * @return array<string, string|null>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id->toBinary(),
            'account_id' => $this->account?->getId()->toBinary(),
            'kind' => $this->kind,
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'error' => $this->error,
            'sent_at' => $this->sentAt->format('Y-m-d H:i:s'),
        ];
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getStatus(): EmailStatus
    {
        return $this->status;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }
}
