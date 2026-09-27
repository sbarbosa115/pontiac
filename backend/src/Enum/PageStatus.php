<?php

declare(strict_types=1);

namespace App\Enum;

/** Where a landing page is in its life: being written, live, or taken down (410 for visitors). */
enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Disabled = 'disabled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
