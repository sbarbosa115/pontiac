<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Account;
use App\Repository\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages anyone can open, rendered on the server (no React) so search engines read them as they are.
 */
final class PublicController extends AbstractController
{
    /** Pontiac's own page. */
    #[Route('/', name: 'public_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('public/home.html.twig');
    }

    /**
     * A consultant's home page (pontiac.co/<slug>). Until the consultant publishes a page it says the site is on its
     * way, and asks search engines not to index it.
     */
    #[Route('/{slug}', name: 'public_account', requirements: ['slug' => Account::SLUG_PATTERN], methods: ['GET'], priority: -10)]
    public function account(string $slug, AccountRepository $accounts): Response
    {
        $account = $accounts->findActiveBySlug($slug) ?? throw $this->createNotFoundException();

        return $this->render('public/account_home.html.twig', ['account' => $account]);
    }
}
