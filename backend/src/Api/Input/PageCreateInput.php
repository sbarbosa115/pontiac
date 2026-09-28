<?php

declare(strict_types=1);

namespace App\Api\Input;

use App\Enum\PageTemplate;
use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/pages: a new page from a template. */
final class PageCreateInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    public ?string $title = null;

    /** /<consultant>/<slug>. Checked by PageEditor. */
    #[Assert\NotBlank]
    public ?string $slug = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [PageTemplate::class, 'values'], message: 'Choose among the templates offered.')]
    public ?string $template = null;
}
