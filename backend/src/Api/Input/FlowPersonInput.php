<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/flows/{id}/people: add a person. */
final class FlowPersonInput
{
    #[Assert\NotBlank(message: 'Choose the person.')]
    public ?string $contactId = null;
}
