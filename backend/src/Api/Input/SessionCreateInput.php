<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/sessions: the consultant books a session for a contact. */
final class SessionCreateInput
{
    #[Assert\NotBlank(message: 'Choose the person.')]
    public ?string $contactId = null;

    /** An active free plan: a new enrollment in it. Either this or enrollmentId. */
    public ?string $planId = null;

    /** A plan the contact has (a paid one, once paid) with sessions left. Either this or planId. */
    public ?string $enrollmentId = null;

    /** ISO 8601 with its offset, e.g. "2026-10-06T15:00:00+00:00". */
    #[Assert\NotBlank(message: 'Choose a day and a time.')]
    #[Assert\DateTime(format: \DATE_ATOM, message: 'Choose a day and a time.')]
    public ?string $startsAt = null;
}
