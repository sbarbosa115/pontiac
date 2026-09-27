<?php

declare(strict_types=1);

namespace App\Enum;

/** Where a payment is: waiting for Wompi, or its result. Only `pending` changes. */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Voided = 'voided';
    // Wompi said something we cannot accept: an error, or an amount, currency or reference that is not ours.
    case Error = 'error';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Wompi's transaction status ("APPROVED", "DECLINED", "VOIDED", "ERROR", "PENDING"). */
    public static function fromWompi(string $status): self
    {
        return match ($status) {
            'APPROVED' => self::Approved,
            'DECLINED' => self::Declined,
            'VOIDED' => self::Voided,
            'PENDING' => self::Pending,
            default => self::Error,
        };
    }
}
