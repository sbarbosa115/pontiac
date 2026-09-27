<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** PATCH /api/admin/client-files/{id}. */
final class ClientFileUpdateInput
{
    #[Assert\NotNull]
    public ?bool $shared = null;
}
