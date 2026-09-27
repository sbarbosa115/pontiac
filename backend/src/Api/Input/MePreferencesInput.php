<?php

declare(strict_types=1);

namespace App\Api\Input;

use App\Enum\UiTheme;
use Symfony\Component\Validator\Constraints as Assert;

/** PATCH /api/me/preferences: what the person chose for themselves. Every field is optional. */
final class MePreferencesInput
{
    #[Assert\Choice(callback: [UiTheme::class, 'values'], message: 'Choose light, dark or system.')]
    public ?string $uiTheme = null;
}
