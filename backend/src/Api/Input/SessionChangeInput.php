<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/sessions/{id}/reschedule: the new start. */
final class SessionChangeInput
{
    #[Assert\NotBlank(message: 'Choose a day and a time.')]
    #[Assert\DateTime(format: \DATE_ATOM, message: 'Choose a day and a time.')]
    public ?string $startsAt = null;
}
