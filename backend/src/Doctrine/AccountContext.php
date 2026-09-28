<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Single place that decides which account the current unit of work belongs to,
 * and keeps AccountScopeFilter in sync with it.
 */
final class AccountContext implements ResetInterface
{
    private ?Account $account = null;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function enterAccount(Account $account): void
    {
        $this->reset();
        $this->account = $account;
        $this->em->getFilters()
            ->getFilter(AccountScopeFilter::NAME)
            // Hex, not the RFC 4122 text: Doctrine stores UUIDs as BINARY(16) on MySQL (see AccountScopeFilter).
            ->setParameter(AccountScopeFilter::PARAMETER, bin2hex($account->getId()->toBinary()));
    }

    /**
     * Cross-account access. Only for ROLE_SUPER_ADMIN on the platform firewall, or trusted maintenance code (a
     * console command that walks every account enters each one instead, whenever it can).
     */
    public function enterPlatformScope(): void
    {
        $this->account = null;
        $filters = $this->em->getFilters();
        if ($filters->isEnabled(AccountScopeFilter::NAME)) {
            $filters->disable(AccountScopeFilter::NAME);
        }
    }

    /**
     * Back to the fail-closed default: filter enabled, no account, matches nothing.
     */
    public function reset(): void
    {
        $this->account = null;
        $filters = $this->em->getFilters();
        // Re-enabling creates a fresh filter instance, which drops any previous parameter.
        if ($filters->isEnabled(AccountScopeFilter::NAME)) {
            $filters->disable(AccountScopeFilter::NAME);
        }
        $filters->enable(AccountScopeFilter::NAME);
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function isPlatformScope(): bool
    {
        return !$this->em->getFilters()->isEnabled(AccountScopeFilter::NAME);
    }
}
