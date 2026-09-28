<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ClientFileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A file of a contact's (Archivos): uploaded by the consultant's team, shared with the client or kept internal, or
 * uploaded by the client from the portal (the team always sees those). Stored under var/uploads, served only through
 * the API. Never deleted: turned off.
 */
#[ORM\Entity(repositoryClass: ClientFileRepository::class)]
#[ORM\Index(name: 'idx_client_file_account_contact', columns: ['account_id', 'contact_id'])]
class ClientFile implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Contact $contact;

    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(length: 100)]
    private string $contentType;

    #[ORM\Column]
    private int $sizeBytes;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $uploadedBy;

    #[ORM\Column]
    private bool $shared;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Contact $contact, Uuid $id, string $name, string $contentType, int $sizeBytes, User $uploadedBy, bool $shared)
    {
        $this->id = $id;
        $this->setAccount($contact->getAccount() ?? throw new \LogicException('A contact belongs to an account.'));
        $this->contact = $contact;
        $this->name = $name;
        $this->contentType = $contentType;
        $this->sizeBytes = $sizeBytes;
        $this->uploadedBy = $uploadedBy;
        // What the client uploads is theirs: they always see it.
        $this->shared = $shared || $uploadedBy->hasRole(User::ROLE_CLIENT);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function isByClient(): bool
    {
        return $this->uploadedBy->hasRole(User::ROLE_CLIENT);
    }

    public function share(bool $shared): static
    {
        $this->shared = $shared || $this->isByClient();

        return $this;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function getUploadedBy(): User
    {
        return $this->uploadedBy;
    }

    public function isShared(): bool
    {
        return $this->shared;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
