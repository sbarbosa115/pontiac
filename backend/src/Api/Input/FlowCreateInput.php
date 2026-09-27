<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/flows: a new flow, from the ready example. */
final class FlowCreateInput
{
    #[Assert\NotBlank(message: 'Give the flow a name.', normalizer: 'trim')]
    #[Assert\Length(max: 120)]
    public ?string $name = null;
}
