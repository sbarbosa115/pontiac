<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST, PUT /api/admin/email-templates. */
final class EmailTemplateInput
{
    #[Assert\NotBlank(message: 'Give the template a name.', normalizer: 'trim')]
    #[Assert\Length(max: 120)]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Write the subject.', normalizer: 'trim')]
    #[Assert\Length(max: 200)]
    public ?string $subject = null;

    #[Assert\NotBlank(message: 'Write the email.', normalizer: 'trim')]
    #[Assert\Length(max: 10000)]
    public ?string $body = null;
}
