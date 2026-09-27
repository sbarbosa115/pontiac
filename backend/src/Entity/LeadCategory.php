<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LeadCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * How a consultant sorts their prospectos (Ajustes › Categorías): "Deudas", "Inversión", "Pensión"… A page gives its
 * leads a default one, and a select field on its form can map each answer to one.
 */
#[ORM\Entity(repositoryClass: LeadCategoryRepository::class)]
#[ORM\Index(name: 'idx_lead_category_account', columns: ['account_id', 'name'])]
class LeadCategory implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    /** The UI tones a category may be shown in (its badge); each is a pair of colour tokens in both themes. */
    public const COLORS = ['info', 'success', 'warning', 'accent', 'teal', 'indigo', 'rose', 'neutral'];

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(length: 20)]
    private string $color;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(Account $account, string $name, string $color = 'info')
    {
        $this->id = Uuid::v7();
        $this->setAccount($account);
        $this->name = $name;
        $this->color = $color;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function rename(string $name, string $color): static
    {
        $this->name = $name;
        $this->color = $color;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
