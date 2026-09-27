<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\AccountOwnedInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Appends "account_id = :current" to every DQL query, find() and lazy
 * collection load of a AccountOwnedInterface entity.
 *
 * Fails closed: while enabled without a account parameter (unauthenticated
 * request, console command, super admin outside the platform API) it matches
 * nothing. It is only disabled explicitly via AccountContext::enterPlatformScope().
 *
 * Doctrine SQL filters do not apply to native SQL or DBAL queries, hence the
 * project rule against using them for account-owned tables.
 */
final class AccountScopeFilter extends SQLFilter
{
    public const NAME = 'account_scope';
    public const PARAMETER = 'account_id';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!is_subclass_of($targetEntity->getName(), AccountOwnedInterface::class)) {
            return '';
        }

        if (!$this->hasParameter(self::PARAMETER)) {
            return '1 = 0';
        }

        // Doctrine's UuidType has no native MySQL counterpart, so account_id is BINARY(16) and a quoted
        // text UUID would never match it. AccountContext passes the hex; UNHEX() of a literal is constant,
        // folded once by the optimizer, and the index on account_id is still used.
        return sprintf(
            '%s.%s = UNHEX(%s)',
            $targetTableAlias,
            $targetEntity->getSingleAssociationJoinColumnName('account'),
            $this->getParameter(self::PARAMETER),
        );
    }
}
