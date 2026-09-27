<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/portal-login: a client signs in at their consultant's portal. */
final class PortalLoginInput
{
    /** The consultant's slug, from the portal's URL (/<slug>/portal). */
    #[Assert\NotBlank]
    public ?string $account = null;

    #[Assert\NotBlank]
    public ?string $email = null;

    #[Assert\NotBlank]
    public ?string $password = null;
}
