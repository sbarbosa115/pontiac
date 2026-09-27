<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What moves a person along an arrow of a flow: something that happened to them, or `manual` (an arrow that only
 * documents a step the consultant takes by hand on the board).
 */
enum FlowTrigger: string
{
    case Manual = 'manual';
    case LeadSubmitted = 'lead_submitted';
    case SessionBooked = 'session_booked';
    case SessionDone = 'session_done';
    case SessionNoShow = 'session_no_show';
    case PaymentApproved = 'payment_approved';
    case EnrollmentCompleted = 'enrollment_completed';
    case ConsultancyFinished = 'consultancy_finished';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
