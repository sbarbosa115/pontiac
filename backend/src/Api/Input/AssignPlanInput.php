<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/contacts/{id}/enrollments and /api/admin/enrollments/{id}/renew. */
final class AssignPlanInput
{
    #[Assert\NotBlank(message: 'Choose a plan.')]
    public ?string $planId = null;
}
