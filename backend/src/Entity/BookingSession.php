<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SessionStatus;
use App\Repository\BookingSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One meeting between the consultant and a contact, in a slot (PRD, "Session"). Times are UTC; the account's timezone
 * is applied when shown. The contact manages it from an emailed link: only the token's hash is kept.
 */
#[ORM\Entity(repositoryClass: BookingSessionRepository::class)]
#[ORM\Table(name: 'session')]
#[ORM\Index(name: 'idx_session_account_starts', columns: ['account_id', 'starts_at'])]
#[ORM\Index(name: 'idx_session_account_status_starts', columns: ['account_id', 'status', 'starts_at'])]
class BookingSession implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    public const BOOKED_BY_VISITOR = 'visitor';
    public const BOOKED_BY_STAFF = 'staff';
    // The client, from the portal: the visitor's rules (free slots, notice, cancellation limit).
    public const BOOKED_BY_CLIENT = 'client';

    #[ORM\ManyToOne(targetEntity: Enrollment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Enrollment $enrollment;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Contact $contact;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(length: 12, enumType: SessionStatus::class)]
    private SessionStatus $status = SessionStatus::Scheduled;

    #[ORM\Column(length: 500)]
    private string $meetingLink;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $manageTokenHash;

    #[ORM\Column(length: 10)]
    private string $bookedBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    // When it last got a new time: a reminder window that had passed before it counts from here.
    #[ORM\Column]
    private \DateTimeImmutable $scheduledAt;

    /**
     * @return array{0: self, 1: string} the session and the plain manage token to email
     */
    public static function book(Enrollment $enrollment, \DateTimeImmutable $startsAt, string $meetingLink, string $bookedBy): array
    {
        $session = new self();
        $session->id = Uuid::v7();
        $session->setAccount($enrollment->getAccount() ?? throw new \LogicException('An enrollment belongs to an account.'));
        $session->enrollment = $enrollment;
        $session->contact = $enrollment->getContact();
        $session->startsAt = $startsAt->setTimezone(new \DateTimeZone('UTC'));
        $session->endsAt = $session->startsAt->modify(sprintf('+%d minutes', $enrollment->getDurationMinutes()));
        $session->meetingLink = $meetingLink;
        $session->bookedBy = $bookedBy;
        $session->createdAt = new \DateTimeImmutable();
        $session->scheduledAt = $session->createdAt;
        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $session->manageTokenHash = self::hashToken($token);

        return [$session, $token];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function __construct()
    {
    }

    public function reschedule(\DateTimeImmutable $startsAt): static
    {
        $this->assertScheduled();
        $this->startsAt = $startsAt->setTimezone(new \DateTimeZone('UTC'));
        $this->endsAt = $this->startsAt->modify(sprintf('+%d minutes', $this->enrollment->getDurationMinutes()));
        $this->scheduledAt = new \DateTimeImmutable();

        return $this;
    }

    public function cancel(?string $reason): static
    {
        $this->assertScheduled();
        $this->status = SessionStatus::Cancelled;
        $this->cancelReason = null === $reason || '' === trim($reason) ? null : trim($reason);

        return $this;
    }

    /** How it went: done or no-show. Only once it has started. */
    public function close(SessionStatus $outcome, \DateTimeImmutable $now): static
    {
        if (!\in_array($outcome, [SessionStatus::Done, SessionStatus::NoShow], true)) {
            throw new \LogicException('A session closes as done or no-show.');
        }
        if (SessionStatus::Cancelled === $this->status) {
            throw new \DomainException('A cancelled session cannot be closed.');
        }
        if ($this->startsAt > $now) {
            throw new \DomainException('A session is closed once it has started.');
        }
        $this->status = $outcome;

        return $this;
    }

    /** Back to scheduled: the consultant marked it by mistake. */
    public function reopen(): static
    {
        if (!\in_array($this->status, [SessionStatus::Done, SessionStatus::NoShow], true)) {
            throw new \DomainException('Only a closed session reopens.');
        }
        $this->status = SessionStatus::Scheduled;

        return $this;
    }

    private function assertScheduled(): void
    {
        if (SessionStatus::Scheduled !== $this->status) {
            throw new \DomainException('The session is no longer scheduled.');
        }
    }

    public function getEnrollment(): Enrollment
    {
        return $this->enrollment;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getStatus(): SessionStatus
    {
        return $this->status;
    }

    public function getMeetingLink(): string
    {
        return $this->meetingLink;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function getBookedBy(): string
    {
        return $this->bookedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    /** The token changes on request only; a new one invalidates the emailed link. */
    public function issueManageToken(): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $this->manageTokenHash = self::hashToken($token);

        return $token;
    }
}
