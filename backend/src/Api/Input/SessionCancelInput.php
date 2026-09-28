<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/sessions/{id}/cancel. */
final class SessionCancelInput
{
    #[Assert\Length(max: 500)]
    public ?string $reason = null;
}
