<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AccountSlugRedirectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A consultant's former address: /<old>/… answers 301 to the same path at the current one. Not account-owned: it is
 * looked up before any account is known, like the account itself.
 */
#[ORM\Entity(repositoryClass: AccountSlugRedirectRepository::class)]
class AccountSlugRedirect
{
    use HasUuid;

    #[ORM\Column(length: 60, unique: true)]
    private string $oldSlug;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    public function __construct(Account $account, string $oldSlug)
    {
        $this->id = Uuid::v7();
        $this->account = $account;
        $this->oldSlug = $oldSlug;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }
}
