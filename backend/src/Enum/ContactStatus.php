<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a person is with the consultant (PRD, "Lead vs client"): a lead until they pay for a plan (milestone 3), a
 * client while they have one, finished when the consultancy ends.
 */
enum ContactStatus: string
{
    case Lead = 'lead';
    case Client = 'client';
    case Finished = 'finished';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
