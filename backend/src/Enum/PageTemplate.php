<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The five designs a consultant builds a landing page from (PRD, "PageTemplate"). The super admin turns each on or off
 * for new pages (Configuración › Plantillas); their sections come with milestone 1.
 */
enum PageTemplate: string
{
    case FreeDiagnostic = 'free_diagnostic';
    case PlanOffer = 'plan_offer';
    case ConsultantProfile = 'consultant_profile';
    case Event = 'event';
    case LeadMagnet = 'lead_magnet';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
