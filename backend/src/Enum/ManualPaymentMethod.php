<?php

declare(strict_types=1);

namespace App\Enum;

/** How a payment the owner records by hand was made. */
enum ManualPaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
