<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/flows/{id}/people/{contactId}/move. */
final class FlowMoveInput
{
    #[Assert\NotBlank(message: 'Choose a stage.')]
    public ?string $stageId = null;
}
