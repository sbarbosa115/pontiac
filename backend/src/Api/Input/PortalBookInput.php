<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/portal/sessions: a session of one of the client's plans. */
final class PortalBookInput
{
    #[Assert\NotBlank(message: 'Choose a plan.')]
    public ?string $enrollmentId = null;

    /** ISO 8601 with its offset, one of the free slots. */
    #[Assert\NotBlank(message: 'Choose a day and a time.')]
    #[Assert\DateTime(format: \DATE_ATOM, message: 'Choose a day and a time.')]
    public ?string $startsAt = null;
}
