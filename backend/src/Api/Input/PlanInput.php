<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST, PUT /api/admin/plans. */
final class PlanInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public ?string $name = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    /** A decimal string: "250000.00", "0". */
    #[Assert\NotBlank]
    #[Assert\Regex('/^\d{1,13}(\.\d{1,2})?$/', message: 'Write an amount with at most two decimals, e.g. "250000.00".')]
    public ?string $price = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 50)]
    public ?int $sessions = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 15, max: 480)]
    public ?int $durationMinutes = null;
}
