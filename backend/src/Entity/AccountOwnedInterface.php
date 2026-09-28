<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Marks an entity as belonging to exactly one consultant's account. Every implementor is automatically scoped by
 * App\Doctrine\AccountScopeFilter and guarded on write by App\Doctrine\AccountOwnershipListener.
 */
interface AccountOwnedInterface
{
    public function getAccount(): ?Account;

    public function setAccount(Account $account): static;
}
