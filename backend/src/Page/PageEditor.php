<?php

declare(strict_types=1);

namespace App\Page;

use App\Api\ApiException;
use App\Api\ApiValidationException;
use App\Entity\Account;
use App\Entity\LandingPage;
use App\Entity\PageSlugRedirect;
use App\Enum\PageStatus;
use App\Enum\PageTemplate;
use App\Repository\LandingPageRepository;
use App\Repository\PageSlugRedirectRepository;
use App\Repository\PlatformSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * What a consultant does to their pages (Páginas): create one from a template, rename it, save its draft, publish,
 * take it down, bring it back, duplicate it, make it the home page. Every save leaves the content valid for its
 * template (ContentValidator); publishing respects the consultant's page limit.
 */
final class PageEditor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LandingPageRepository $pages,
        private readonly PageSlugRedirectRepository $redirects,
        private readonly PlatformSettingsRepository $settings,
        private readonly ContentValidator $validator,
    ) {
    }

    public function create(Account $account, string $title, string $slug, PageTemplate $template): LandingPage
    {
        if (!\in_array($template, $this->settings->current()->getEnabledTemplates(), true)) {
            throw ApiValidationException::single('template', 'This template is not available.');
        }
        $slug = $this->assertSlugAvailable($slug, null);

        $page = new LandingPage($account, $title, $slug, $template, TemplateCatalog::newContent($template, $title));
        // The first page is the consultant's home page (/<consultant>).
        $page->setHome(null === $this->pages->findHome());
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    /**
     * @param array<string, mixed>|null $content the whole draft, or null to leave it
     */
    public function update(LandingPage $page, ?string $title, ?string $slug, ?array $content): LandingPage
    {
        if (null !== $content) {
            $page->saveDraft($this->validator->validate($page->getTemplate(), $content));
        }
        $newSlug = null === $slug ? $page->getSlug() : $this->assertSlugAvailable($slug, $page);
        if ($newSlug !== $page->getSlug()) {
            // A live address keeps working: the old one sends visitors (and search engines) to the new one.
            if (null !== $page->getPublished()) {
                $this->em->persist(new PageSlugRedirect($page, $page->getSlug()));
            }
            $this->dropRedirectFrom($newSlug);
        }
        $page->rename(null === $title ? $page->getTitle() : $title, $newSlug);
        $this->em->flush();

        return $page;
    }

    public function publish(LandingPage $page): LandingPage
    {
        $this->validator->assertPublishable($page->getTemplate(), $page->getDraft());
        if (PageStatus::Published !== $page->getStatus()) {
            $this->assertRoomToPublish($page);
        }
        $page->publish();
        $this->em->flush();

        return $page;
    }

    public function disable(LandingPage $page): LandingPage
    {
        $page->disable();
        $this->em->flush();

        return $page;
    }

    public function reactivate(LandingPage $page): LandingPage
    {
        if (null !== $page->getPublished()) {
            $this->assertRoomToPublish($page);
        }
        $page->reactivate();
        $this->em->flush();

        return $page;
    }

    /** A copy to work from: same template and draft, a free address, not published, not the home page. */
    public function duplicate(LandingPage $page): LandingPage
    {
        $account = $page->getAccount() ?? throw new \LogicException('A page belongs to an account.');
        $base = mb_substr($page->getSlug(), 0, 52).'-copia';
        $slug = $base;
        for ($n = 2; null !== $this->pages->findOneBySlug($slug) || null !== $this->redirects->findOneByOldSlug($slug); ++$n) {
            $slug = $base.'-'.$n;
        }

        $copy = new LandingPage($account, mb_substr($page->getTitle().' (copia)', 0, 160), $slug, $page->getTemplate(), $page->getDraft());
        $this->em->persist($copy);
        $this->em->flush();

        return $copy;
    }

    public function makeHome(LandingPage $page): LandingPage
    {
        $current = $this->pages->findHome();
        if (null !== $current && !$current->getId()->equals($page->getId())) {
            $current->setHome(false);
            // Two rows cannot be "home" at once in the same flush order Doctrine picks: clear the old one first.
            $this->em->flush();
        }
        $page->setHome(true);
        $this->em->flush();

        return $page;
    }

    private function assertRoomToPublish(LandingPage $page): void
    {
        $account = $page->getAccount() ?? throw new \LogicException('A page belongs to an account.');
        if ($this->pages->countPublished() >= $account->getMaxPublishedPages()) {
            throw ApiException::conflict('page_limit_reached', sprintf('This consultant can have at most %d published pages.', $account->getMaxPublishedPages()));
        }
    }

    private function assertSlugAvailable(string $slug, ?LandingPage $for): string
    {
        $slug = Account::normalizeSlug($slug);
        if (1 !== preg_match('/^'.Account::SLUG_PATTERN.'$/', $slug)) {
            throw ApiValidationException::single('slug', 'Use 3 to 60 lowercase letters, numbers and hyphens.');
        }
        if (LandingPage::isReservedSlug($slug)) {
            throw ApiValidationException::single('slug', 'This address is reserved.');
        }
        $owner = $this->pages->findOneBySlug($slug);
        if (null !== $owner && (null === $for || !$owner->getId()->equals($for->getId()))) {
            throw ApiValidationException::single('slug', 'Another of your pages already uses this address.');
        }

        return $slug;
    }

    /** An address a page takes stops sending visitors elsewhere. */
    private function dropRedirectFrom(string $slug): void
    {
        $redirect = $this->redirects->findOneByOldSlug($slug);
        if (null !== $redirect) {
            $this->em->remove($redirect);
        }
    }
}
