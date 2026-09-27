<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/platform/accounts: a new consultant and the owner to invite. */
final class AccountCreateInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    public ?string $name = null;

    /** Their address: pontiac.co/<slug>. Checked by AccountSlugPolicy. */
    #[Assert\NotBlank]
    public ?string $slug = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    public ?string $ownerName = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $ownerEmail = null;
}
