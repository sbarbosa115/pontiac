<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Account;
use App\Entity\LandingPage;
use App\Enum\PageStatus;
use App\Media\MediaStorage;
use App\Page\LeadIntake;
use App\Page\PageRenderer;
use App\Page\PublicResolver;
use App\Repository\AccountRepository;
use App\Repository\LandingPageRepository;
use App\Repository\MediaAssetRepository;
use App\Repository\PlatformSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * What anyone can open, rendered on the server for search engines: Pontiac's page, each consultant's pages, their
 * forms, privacy policy, images and sitemap. A consultant's data is reached only after its address (slug) names an
 * active account, which the request then enters (AccountContext): the account filter does the rest.
 */
final class PublicController extends AbstractController
{
    private const UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly LandingPageRepository $pages,
        private readonly PublicResolver $resolver,
        private readonly PageRenderer $renderer,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
    ) {
    }

    /** Pontiac's own page. */
    #[Route('/', name: 'public_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('public/home.html.twig');
    }

    #[Route('/robots.txt', name: 'public_robots', methods: ['GET'], priority: 20)]
    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /plataforma', 'Disallow: /api/', 'Disallow: /login', 'Disallow: /invitacion', 'Disallow: /*/portal', 'Allow: /', '', 'Sitemap: '.rtrim($this->appUrl, '/').'/sitemap.xml'];

        return new Response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** One sitemap per consultant, listed here. */
    #[Route('/sitemap.xml', name: 'public_sitemap_index', methods: ['GET'], priority: 20)]
    public function sitemapIndex(): Response
    {
        $urls = array_map(fn (Account $account) => rtrim($this->appUrl, '/').'/'.$account->getSlug().'/sitemap.xml', $this->accounts->findBy(['active' => true], ['slug' => 'ASC']));

        return $this->render('public/sitemap_index.xml.twig', ['urls' => $urls], new Response(null, 200, ['Content-Type' => 'application/xml; charset=UTF-8']));
    }

    #[Route('/{slug}/sitemap.xml', name: 'public_sitemap', requirements: ['slug' => Account::SLUG_PATTERN], methods: ['GET'], priority: 5)]
    public function sitemap(string $slug, Request $request): Response
    {
        $account = $this->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }

        $base = rtrim($this->appUrl, '/').'/'.$account->getSlug();
        $urls = array_map(static fn (LandingPage $page) => [
            'loc' => $base.($page->isHome() ? '' : '/'.$page->getSlug()),
            'lastmod' => $page->getPublishedAt()?->format('Y-m-d'),
        ], $this->pages->findIndexable());

        return $this->render('public/sitemap.xml.twig', ['urls' => $urls], new Response(null, 200, ['Content-Type' => 'application/xml; charset=UTF-8']));
    }

    #[Route('/{slug}/privacidad', name: 'public_privacy', requirements: ['slug' => Account::SLUG_PATTERN], methods: ['GET'], priority: 5)]
    public function privacy(string $slug, Request $request, PlatformSettingsRepository $settings): Response
    {
        $account = $this->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }

        $text = '' !== $account->getPrivacyText() ? $account->getPrivacyText() : $settings->current()->getDefaultPrivacyText();

        return $this->render('public/privacy.html.twig', ['account' => $account, 'text' => $text]);
    }

    /** A library image at one of its widths. Long cache: an image's URL changes when the image does. */
    #[Route('/{slug}/media/{id}-{width}.webp', name: 'public_media', requirements: ['slug' => Account::SLUG_PATTERN, 'id' => Requirement::UUID, 'width' => '\d{2,4}'], methods: ['GET'], priority: 5)]
    public function media(string $slug, string $id, int $width, Request $request, MediaAssetRepository $media, MediaStorage $storage): Response
    {
        $account = $this->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $asset = $media->findOneById($id);
        if (null === $asset || !\in_array($width, $asset->getVariants(), true) || !is_file($storage->variantPath($asset, $width))) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($storage->variantPath($asset, $width), 200, ['Content-Type' => 'image/webp'], true, null, false, true);
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->headers->addCacheControlDirective('immutable');

        return $response;
    }

    /** The consultant's home page, or "coming soon" until they publish one. */
    #[Route('/{slug}', name: 'public_account', requirements: ['slug' => Account::SLUG_PATTERN], methods: ['GET'], priority: -10)]
    public function account(string $slug, Request $request): Response
    {
        $account = $this->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $home = $this->pages->findHome();
        if (null === $home || PageStatus::Published !== $home->getStatus()) {
            return $this->render('public/account_home.html.twig', ['account' => $account]);
        }

        return $this->show($account, $home, $request);
    }

    #[Route('/{slug}/{page}', name: 'public_page', requirements: ['slug' => Account::SLUG_PATTERN, 'page' => Account::SLUG_PATTERN], methods: ['GET'], priority: -10)]
    public function page(string $slug, string $page, Request $request): Response
    {
        $account = $this->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $found = $this->find($account, $page);
        if ($found instanceof Response) {
            return $found;
        }
        // The home page lives at /<consultant>: one address per page, so search engines do not see it twice.
        if ($found->isHome()) {
            return new RedirectResponse('/'.$account->getSlug(), 301);
        }

        return $this->show($account, $found, $request);
    }

    #[Route('/{slug}/enviar', name: 'public_home_submit', requirements: ['slug' => Account::SLUG_PATTERN], methods: ['POST'], priority: -5)]
    public function submitHome(string $slug, Request $request, LeadIntake $intake, #[Autowire(service: 'limiter.lead_form')] RateLimiterFactoryInterface $limiter): Response
    {
        $account = $this->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $home = $this->pages->findHome() ?? throw $this->createNotFoundException();

        return $this->submit($account, $home, $request, $intake, $limiter);
    }

    #[Route('/{slug}/{page}/enviar', name: 'public_page_submit', requirements: ['slug' => Account::SLUG_PATTERN, 'page' => Account::SLUG_PATTERN], methods: ['POST'], priority: -10)]
    public function submitPage(string $slug, string $page, Request $request, LeadIntake $intake, #[Autowire(service: 'limiter.lead_form')] RateLimiterFactoryInterface $limiter): Response
    {
        $account = $this->enter($slug, $request);
        if ($account instanceof Response) {
            return $account;
        }
        $found = $this->find($account, $page);

        return $found instanceof Response ? $found : $this->submit($account, $found, $request, $intake, $limiter);
    }

    /**
     * A reload of the page a form came back to with errors asks for its address with GET: back to the page itself.
     */
    #[Route('/{slug}/enviar', name: 'public_home_submit_reload', requirements: ['slug' => Account::SLUG_PATTERN], methods: ['GET'], priority: -5)]
    #[Route('/{slug}/{page}/enviar', name: 'public_page_submit_reload', requirements: ['slug' => Account::SLUG_PATTERN, 'page' => Account::SLUG_PATTERN], methods: ['GET'], priority: -10)]
    public function submitReload(string $slug, ?string $page = null): Response
    {
        return new RedirectResponse('/'.$slug.(null === $page ? '' : '/'.$page).'#formulario', 303);
    }

    private function show(Account $account, LandingPage $page, Request $request): Response
    {
        if (PageStatus::Disabled === $page->getStatus()) {
            return $this->render('public/gone.html.twig', ['account' => $account], new Response(null, 410));
        }
        $content = $page->getPublished();
        if (PageStatus::Published !== $page->getStatus() || null === $content) {
            throw $this->createNotFoundException();
        }

        $sent = $request->query->has('enviado');
        $booked = $request->query->has('reservado');
        // A page with a booking section shows free slots, which change with every booking: not cached then.
        if ($this->hasBooking($content)) {
            return $this->renderer->render($account, $page, $content, sent: $sent, booking: ['booked' => $booked])->setPrivate();
        }
        $etag = md5(implode('|', [PageRenderer::DESIGN, $page->getId(), $page->getPublishedAt()?->format('U'), $page->isHome() ? 1 : 0, $account->getName(), $account->getSlug(), $sent ? 1 : 0]));
        $cached = (new Response())->setEtag($etag)->setPublic()->setMaxAge(300);
        if ($cached->isNotModified($request)) {
            return $cached;
        }

        return $this->renderer->render($account, $page, $content, sent: $sent)->setEtag($etag)->setPublic()->setMaxAge(300);
    }

    /**
     * @param array<string, mixed> $content
     */
    private function hasBooking(array $content): bool
    {
        foreach ($content['sections'] as $section) {
            if ('booking' === $section['type'] && $section['enabled']) {
                return true;
            }
        }

        return false;
    }

    private function submit(Account $account, LandingPage $page, Request $request, LeadIntake $intake, RateLimiterFactoryInterface $limiter): Response
    {
        $content = $page->getPublished();
        if (PageStatus::Published !== $page->getStatus() || null === $content) {
            throw $this->createNotFoundException();
        }
        // Post, redirect, get: a reload after sending does not send again.
        $done = new RedirectResponse('/'.$account->getSlug().($page->isHome() ? '' : '/'.$page->getSlug()).'?enviado=1#formulario', 303);

        $data = $request->request->all();
        if (!$intake->isHuman($data)) {
            return $done;
        }
        $checked = $intake->check($content, $data);
        if ([] !== $checked['errors']) {
            return $this->renderer->render($account, $page, $content, values: $checked['values'], errors: $checked['errors'], status: 422);
        }
        if (!$limiter->create($page->getId().'|'.$request->getClientIp())->consume()->isAccepted()) {
            return $this->renderer->render($account, $page, $content, values: $checked['values'], errors: ['_form' => 'Recibimos muchos envíos seguidos. Espera unos minutos e inténtalo de nuevo.'], status: 429);
        }

        // The campaign that brought them, from the page's address (a form keeps the query string it was shown with).
        parse_str((string) parse_url((string) $request->headers->get('referer'), \PHP_URL_QUERY), $query);
        $utm = [];
        foreach (self::UTM as $key) {
            $value = $query[$key] ?? null;
            if (\is_string($value) && '' !== $value) {
                $utm[$key] = mb_substr($value, 0, 200);
            }
        }
        $intake->record($account, $page, $content, $checked['values'], $utm, $request->headers->get('referer'));

        return $done;
    }

    private function enter(string $slug, Request $request): Account|Response
    {
        return $this->resolver->enter($slug, $request);
    }

    private function find(Account $account, string $slug): LandingPage|Response
    {
        return $this->resolver->page($account, $slug);
    }
}
