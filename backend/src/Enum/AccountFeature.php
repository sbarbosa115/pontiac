<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What a consultant may use, turned on or off by the super admin (Asesores › Límites y funciones). A feature that is off
 * hides its menu and answers 403 feature_disabled.
 */
enum AccountFeature: string
{
    case Booking = 'booking';
    case Payments = 'payments';
    case Portal = 'portal';
    case Flows = 'flows';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
