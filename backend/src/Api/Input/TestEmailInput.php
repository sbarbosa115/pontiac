<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/platform/emails/test. */
final class TestEmailInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $to = null;
}
