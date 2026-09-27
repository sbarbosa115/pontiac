<?php

declare(strict_types=1);

namespace App\Api\Input;

use App\Entity\LeadCategory;
use Symfony\Component\Validator\Constraints as Assert;

/** POST, PUT /api/admin/categories. */
final class LeadCategoryInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: LeadCategory::COLORS, message: 'Choose one of the colours offered.')]
    public ?string $color = null;
}
