<?php

declare(strict_types=1);

namespace App\Enum;

/** Where a session is: booked, or how it ended. */
enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Done = 'done';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
