<?php

declare(strict_types=1);

namespace App\Enum;

/** Who reads a session note: the owner only, or also the client (in the portal) and the assistant. */
enum NoteVisibility: string
{
    case Private = 'private';
    case Shared = 'shared';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
