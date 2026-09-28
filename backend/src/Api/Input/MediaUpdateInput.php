<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** PATCH /api/admin/media/{id}. */
final class MediaUpdateInput
{
    #[Assert\NotNull]
    #[Assert\Length(max: 255)]
    public ?string $altText = null;
}
