<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /api/admin/pages/{id}: the editor's save. A field left out (or null) is left as it is; `draft` is the whole
 * draft (sections, form, seo, settings), checked against the template by ContentValidator.
 */
final class PageUpdateInput
{
    #[Assert\Length(min: 1, max: 160)]
    public ?string $title = null;

    public ?string $slug = null;

    /** @var array<string, mixed>|null */
    public ?array $draft = null;
}
