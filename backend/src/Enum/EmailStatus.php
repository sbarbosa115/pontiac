<?php

declare(strict_types=1);

namespace App\Enum;

/** How one attempt to send an email ended. */
enum EmailStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
