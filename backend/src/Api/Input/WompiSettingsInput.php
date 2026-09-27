<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** PUT /api/admin/wompi. The secrets are optional: empty keeps the one stored. Checked by App\Payment\WompiKeys. */
final class WompiSettingsInput
{
    #[Assert\NotBlank(message: 'Paste the public key: it starts with "pub_test_" or "pub_prod_".')]
    #[Assert\Length(max: 120)]
    public ?string $publicKey = null;

    #[Assert\Length(max: 200)]
    public ?string $privateKey = null;

    #[Assert\Length(max: 200)]
    public ?string $eventsSecret = null;

    #[Assert\Length(max: 200)]
    public ?string $integritySecret = null;
}
