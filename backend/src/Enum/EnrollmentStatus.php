<?php

declare(strict_types=1);

namespace App\Enum;

/** One contact taking one plan once (PRD, "Enrollment"). Payment (pending_payment) comes with milestone 3. */
enum EnrollmentStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
