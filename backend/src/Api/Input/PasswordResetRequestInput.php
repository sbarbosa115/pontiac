<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/password-reset/request. */
final class PasswordResetRequestInput
{
    #[Assert\NotBlank(message: 'Write your email.')]
    #[Assert\Email(message: 'Write a valid email, e.g. name@mail.com.')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    /** The consultant's slug, when a client asks from their portal; absent for staff. */
    public ?string $account = null;
}
