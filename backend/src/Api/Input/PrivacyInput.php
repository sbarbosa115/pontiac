<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** PUT /api/admin/privacy: the consultant's privacy policy; empty goes back to the platform's default. */
final class PrivacyInput
{
    #[Assert\NotNull]
    #[Assert\Length(max: 60000)]
    public ?string $text = null;
}
