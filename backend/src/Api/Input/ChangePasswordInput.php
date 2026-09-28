<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/me/password. */
final class ChangePasswordInput
{
    #[Assert\NotBlank(message: 'Write your current password.')]
    public ?string $currentPassword = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 12, max: 4096)]
    public ?string $newPassword = null;
}
