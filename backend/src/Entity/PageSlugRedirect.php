<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PageSlugRedirectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** A page's former address: /<consultant>/<old> answers 301 to the page's current one, so links and rankings survive. */
#[ORM\Entity(repositoryClass: PageSlugRedirectRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_page_redirect_account_slug', columns: ['account_id', 'old_slug'])]
class PageSlugRedirect implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\Column(length: 60)]
    private string $oldSlug;

    #[ORM\ManyToOne(targetEntity: LandingPage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private LandingPage $page;

    public function __construct(LandingPage $page, string $oldSlug)
    {
        $this->id = Uuid::v7();
        $this->setAccount($page->getAccount() ?? throw new \LogicException('A page belongs to an account.'));
        $this->page = $page;
        $this->oldSlug = $oldSlug;
    }

    public function getOldSlug(): string
    {
        return $this->oldSlug;
    }

    public function getPage(): LandingPage
    {
        return $this->page;
    }
}
