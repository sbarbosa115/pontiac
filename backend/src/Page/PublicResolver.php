<?php

declare(strict_types=1);

namespace App\Page;

use App\Doctrine\AccountContext;
use App\Entity\Account;
use App\Entity\LandingPage;
use App\Repository\AccountRepository;
use App\Repository\AccountSlugRedirectRepository;
use App\Repository\LandingPageRepository;
use App\Repository\PageSlugRedirectRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * How a public route reaches a consultant's data: the active account at the URL's address, entered (AccountContext),
 * or a 301 when the address is a former one; then the page at its address, or a 301 from a page's former address.
 * Anything else is a 404.
 */
final class PublicResolver
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly AccountSlugRedirectRepository $accountRedirects,
        private readonly LandingPageRepository $pages,
        private readonly PageSlugRedirectRepository $pageRedirects,
        private readonly AccountContext $context,
    ) {
    }

    public function enter(string $slug, Request $request): Account|Response
    {
        $account = $this->accounts->findActiveBySlug($slug);
        if (null !== $account) {
            $this->context->enterAccount($account);

            return $account;
        }

        $redirect = $this->accountRedirects->findOneByOldSlug(Account::normalizeSlug($slug));
        if (null !== $redirect && $redirect->getAccount()->isActive()) {
            $path = '/'.$redirect->getAccount()->getSlug().substr($request->getPathInfo(), \strlen('/'.$slug));
            $query = $request->getQueryString();

            return new RedirectResponse($path.(null === $query ? '' : '?'.$query), 301);
        }

        throw new NotFoundHttpException();
    }

    public function page(Account $account, string $slug): LandingPage|Response
    {
        $page = $this->pages->findOneBySlug($slug);
        if (null !== $page) {
            return $page;
        }
        $redirect = $this->pageRedirects->findOneByOldSlug(Account::normalizeSlug($slug));
        if (null !== $redirect) {
            $target = $redirect->getPage();

            return new RedirectResponse('/'.$account->getSlug().($target->isHome() ? '' : '/'.$target->getSlug()), 301);
        }

        throw new NotFoundHttpException();
    }

    public function home(): ?LandingPage
    {
        return $this->pages->findHome();
    }
}
