<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The account_id column of a customer-owned table. Kept on every owned table, even where it could be derived from a
 * parent, so isolation is one indexed equality check; indexes on these tables lead with it.
 */
trait AccountOwnedTrait
{
    // The filter matches on this exact column name.
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', nullable: false)]
    private ?Account $account = null;

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): static
    {
        if (null !== $this->account && !$this->account->getId()->equals($account->getId())) {
            throw new \LogicException(sprintf('%s already belongs to another account.', static::class));
        }
        $this->account = $account;

        return $this;
    }
}
