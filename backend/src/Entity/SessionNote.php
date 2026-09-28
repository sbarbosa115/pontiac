<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NoteVisibility;
use App\Repository\SessionNoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * What was said in a session. Private notes are the owner's alone (financial details are sensitive); shared ones
 * are read by the assistant and, from milestone 4, by the client in the portal.
 */
#[ORM\Entity(repositoryClass: SessionNoteRepository::class)]
#[ORM\Index(name: 'idx_session_note_account_session', columns: ['account_id', 'session_id'])]
class SessionNote implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\ManyToOne(targetEntity: BookingSession::class)]
    #[ORM\JoinColumn(nullable: false)]
    private BookingSession $session;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(length: 10, enumType: NoteVisibility::class)]
    private NoteVisibility $visibility;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(BookingSession $session, User $author, string $body, NoteVisibility $visibility)
    {
        $this->id = Uuid::v7();
        $this->setAccount($session->getAccount() ?? throw new \LogicException('A session belongs to an account.'));
        $this->session = $session;
        $this->author = $author;
        $this->body = $body;
        $this->visibility = $visibility;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function change(string $body, NoteVisibility $visibility): static
    {
        $this->body = $body;
        $this->visibility = $visibility;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getSession(): BookingSession
    {
        return $this->session;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getVisibility(): NoteVisibility
    {
        return $this->visibility;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
