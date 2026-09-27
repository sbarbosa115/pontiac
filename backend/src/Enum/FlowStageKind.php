<?php

declare(strict_types=1);

namespace App\Enum;

/** Where a stage sits in a flow: where people enter (one per flow), a step, or an end. */
enum FlowStageKind: string
{
    case Start = 'start';
    case Step = 'step';
    case End = 'end';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
