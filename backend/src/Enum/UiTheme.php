<?php

declare(strict_types=1);

namespace App\Enum;

/** How the app looks for a person: light, dark, or whatever their device is set to. */
enum UiTheme: string
{
    case Light = 'light';
    case Dark = 'dark';
    // Follows the operating system's setting (prefers-color-scheme), live.
    case System = 'system';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
