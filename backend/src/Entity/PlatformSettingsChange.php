<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlatformSettingsChangeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One save of Configuración: who changed which settings, from what to what (Configuración › Historial).
 */
#[ORM\Entity(repositoryClass: PlatformSettingsChangeRepository::class)]
#[ORM\Index(name: 'idx_settings_change_at', columns: ['changed_at'])]
class PlatformSettingsChange
{
    use HasUuid;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $changedBy;

    /** @var array<string, array{from: mixed, to: mixed}> by setting name */
    #[ORM\Column(type: Types::JSON)]
    private array $changes;

    // The names of the settings changed, space-separated: what the search box looks at.
    #[ORM\Column(type: Types::TEXT)]
    private string $fields;

    #[ORM\Column]
    private \DateTimeImmutable $changedAt;

    /**
     * @param array<string, array{from: mixed, to: mixed}> $changes
     */
    public function __construct(User $changedBy, array $changes)
    {
        $this->id = Uuid::v7();
        $this->changedBy = $changedBy;
        $this->changes = $changes;
        $this->fields = implode(' ', array_keys($changes));
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getChangedBy(): User
    {
        return $this->changedBy;
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }
}
