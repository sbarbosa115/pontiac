<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/sessions: the consultant books a session for a contact. */
final class SessionCreateInput
{
    #[Assert\NotBlank]
    public ?string $contactId = null;

    /** An active free plan (a paid one is booked after its payment). */
    #[Assert\NotBlank]
    public ?string $planId = null;

    /** ISO 8601 with its offset, e.g. "2026-10-06T15:00:00+00:00". */
    #[Assert\NotBlank]
    #[Assert\DateTime(format: \DATE_ATOM, message: 'Choose a day and a time.')]
    public ?string $startsAt = null;
}
