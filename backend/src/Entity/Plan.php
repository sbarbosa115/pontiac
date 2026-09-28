<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Something the consultant sells (Planes): a price for so many sessions of so many minutes. "Diagnóstico: $0 × 1 ×
 * 45 min", "Plan A: $250.000 × 2 × 60 min". Free plans are booked straight from a page; paid ones after payment.
 */
#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\Index(name: 'idx_plan_account_name', columns: ['account_id', 'name'])]
class Plan implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    // A decimal string, never a float (NUMERIC(15,2)).
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $price;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $sessions;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $durationMinutes;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(Account $account, string $name, string $description, string $price, int $sessions, int $durationMinutes)
    {
        $this->id = Uuid::v7();
        $this->setAccount($account);
        $this->name = $name;
        $this->description = $description;
        $this->price = self::amount($price);
        $this->currency = $account->getCurrency();
        $this->sessions = $sessions;
        $this->durationMinutes = $durationMinutes;
    }

    public function change(string $name, string $description, string $price, int $sessions, int $durationMinutes): static
    {
        $this->name = $name;
        $this->description = $description;
        $this->price = self::amount($price);
        $this->sessions = $sessions;
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function isFree(): bool
    {
        return '0.00' === $this->price;
    }

    /** "250000" and "250000.5" as the database keeps them: "250000.00", "250000.50". */
    private static function amount(string $price): string
    {
        [$units, $cents] = explode('.', $price.'.', 3);

        return (ltrim($units, '0') ?: '0').'.'.str_pad(substr($cents, 0, 2), 2, '0');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getSessions(): int
    {
        return $this->sessions;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
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
