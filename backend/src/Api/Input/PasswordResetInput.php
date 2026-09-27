<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/password-reset/confirm. */
final class PasswordResetInput
{
    #[Assert\NotBlank]
    public ?string $token = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 12, max: 4096)]
    public ?string $password = null;
}
