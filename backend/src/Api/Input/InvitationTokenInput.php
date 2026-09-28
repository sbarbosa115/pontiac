<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class InvitationTokenInput
{
    #[Assert\NotBlank]
    public ?string $token = null;
}
