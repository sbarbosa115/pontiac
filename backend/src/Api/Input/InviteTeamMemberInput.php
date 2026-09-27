<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/team: invite an assistant. */
final class InviteTeamMemberInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    public ?string $fullName = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;
}
