<?php

declare(strict_types=1);

namespace App\Api\Input;

use App\Enum\NoteVisibility;
use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/admin/sessions/{id}/notes, PUT /api/admin/session-notes/{id}. */
final class SessionNoteInput
{
    #[Assert\NotBlank(message: 'Write the note.', normalizer: 'trim')]
    #[Assert\Length(max: 5000)]
    public ?string $body = null;

    /** "private" (the owner only) or "shared" (the assistant and, in the portal, the client). */
    #[Assert\NotBlank(message: 'Choose who can read it.')]
    #[Assert\Choice(callback: [NoteVisibility::class, 'values'], message: 'Choose who can read it.')]
    public ?string $visibility = null;
}
