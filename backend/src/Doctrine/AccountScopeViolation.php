<?php

declare(strict_types=1);

namespace App\Doctrine;

/**
 * Thrown when a write would mix data from different accounts. Always a bug, never user error.
 */
final class AccountScopeViolation extends \LogicException
{
}
