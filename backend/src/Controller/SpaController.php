<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Account;
use App\Repository\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the React app for the paths it owns; React Router handles the rest client-side. Everything else outside
 * /api is a public page rendered by Symfony (PublicController), for search engines.
 */
final class SpaController extends AbstractController
{
    #[Route(
        '/{section}/{path}',
        name: 'app_spa',
        requirements: ['section' => 'login|invitacion|admin|plataforma', 'path' => '.*'],
        defaults: ['path' => ''],
        methods: ['GET'],
        priority: 10,
    )]
    public function app(): Response
    {
        return $this->render('spa.html.twig');
    }

    /**
     * A consultant's client portal. A slug that is not an active consultant is a 404 here, before any JavaScript.
     */
    #[Route(
        '/{slug}/portal/{path}',
        name: 'app_portal',
        requirements: ['slug' => Account::SLUG_PATTERN, 'path' => '.*'],
        defaults: ['path' => ''],
        methods: ['GET'],
        priority: 10,
    )]
    public function portal(string $slug, AccountRepository $accounts): Response
    {
        if (null === $accounts->findActiveBySlug($slug)) {
            throw $this->createNotFoundException();
        }

        return $this->render('spa.html.twig');
    }
}
