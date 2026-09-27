<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\AccountFeature;

/**
 * On a controller class or action: the user's account must have this feature (Asesores › Límites y funciones), or the
 * request is a 403 feature_disabled. Repeat it when several are needed. Checked by App\EventSubscriber\FeatureGateSubscriber before the action runs.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class RequiresFeature
{
    public function __construct(public AccountFeature $feature)
    {
    }
}
