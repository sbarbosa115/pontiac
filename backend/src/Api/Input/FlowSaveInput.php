<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** PUT /api/admin/flows/{id}: the whole canvas. Stages and arrows are checked by App\Flow\FlowGraph. */
final class FlowSaveInput
{
    #[Assert\NotBlank(message: 'Give the flow a name.', normalizer: 'trim')]
    #[Assert\Length(max: 120)]
    public ?string $name = null;

    /**
     * Each: id (an existing stage's, or any new one's like "new-1"), name, kind, x, y, emailTemplateId, alertDays.
     *
     * @var list<mixed>|null
     */
    #[Assert\NotNull]
    public ?array $stages = null;

    /**
     * Each: from and to (stage ids as in `stages`), trigger.
     *
     * @var list<mixed>|null
     */
    #[Assert\NotNull]
    public ?array $transitions = null;
}
