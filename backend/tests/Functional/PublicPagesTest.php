<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\PageTemplate;
use App\Tests\Functional\Api\ApiTestCase;

/**
 * What visitors and search engines get: server-rendered pages with their meta data, one address per page (former ones
 * redirect), nothing unpublished, sitemaps and robots.txt.
 */
final class PublicPagesTest extends ApiTestCase
{
    public function testAPublishedPageIsCompleteForSearchEngines(): void
    {
        $account = $this->createAccount();
        $this->createPage($account, 'diagnostico', change: static function (array $content): array {
            $content['seo']['description'] = 'Una sesión gratuita para ordenar tus finanzas.';

            return $content;
        });

        $crawler = $this->client->request('GET', '/finanzas-claras/diagnostico');

        self::assertSame(200, $this->responseStatus());
        self::assertCount(1, $crawler->filter('h1'), 'one h1');
        self::assertSelectorTextContains('h1', 'Ordena tus finanzas con un diagnóstico gratuito');
        self::assertSame('Página diagnostico', $crawler->filter('title')->text());
        self::assertSame('Una sesión gratuita para ordenar tus finanzas.', $crawler->filter('meta[name="description"]')->attr('content'));
        self::assertSame('http://localhost:8080/finanzas-claras/diagnostico', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame('es-CO', $crawler->filter('html')->attr('lang'));
        self::assertSelectorNotExists('meta[name="robots"]');
        self::assertSelectorNotExists('script[src]', 'no framework JavaScript');

        $jsonLd = json_decode($crawler->filter('script[type="application/ld+json"]')->text(), true);
        self::assertSame(['ProfessionalService', 'FAQPage'], array_column($jsonLd['@graph'], '@type'));
        self::assertSame('¿Cuánto dura una sesión?', $jsonLd['@graph'][1]['mainEntity'][0]['name']);
    }

    public function testASectionSwitchedOffIsNotShown(): void
    {
        $this->createPage($this->createAccount(), change: static function (array $content): array {
            $content['sections'][5]['enabled'] = false; // preguntas

            return $content;
        });

        $crawler = $this->client->request('GET', '/finanzas-claras/diagnostico');

        self::assertSelectorNotExists('#preguntas');
        self::assertSame(['ProfessionalService'], array_column(json_decode($crawler->filter('script[type="application/ld+json"]')->text(), true)['@graph'], '@type'));
    }

    public function testEveryButtonLeadsToTheFirstWayToActWhereverItIs(): void
    {
        $account = $this->createAccount();
        $planId = (string) $this->createPlan($account)->getId();
        $this->createPage($account);
        $this->createPage($account, 'con-reserva', change: static fn (array $content): array => self::changeSection($content, 'reserva', ['enabled' => true, 'planId' => $planId]));
        $this->createPage($account, 'formulario-arriba', change: static function (array $content) use ($planId): array {
            $content = self::changeSection($content, 'reserva', ['enabled' => true, 'planId' => $planId]);
            $form = array_values(array_filter($content['sections'], static fn (array $s) => 'formulario' === $s['id']));
            $content['sections'] = [...$form, ...array_values(array_filter($content['sections'], static fn (array $s) => 'formulario' !== $s['id']))];

            return $content;
        });
        $this->createPage($account, 'sin-formulario', change: static fn (array $content): array => self::changeSection($content, 'formulario', ['enabled' => false]));

        $this->assertButtonsLeadTo('/finanzas-claras/diagnostico', '#formulario', 'booking is off: the form');
        $this->assertButtonsLeadTo('/finanzas-claras/con-reserva', '#reserva', 'the booking comes before the form');
        $crawler = $this->assertButtonsLeadTo('/finanzas-claras/formulario-arriba', '#formulario', 'moved above the booking');
        self::assertCount(1, $crawler->filter('h1'), 'one h1, whatever comes first');
        self::assertSame('Agenda tu diagnóstico gratuito', $crawler->filter('#formulario h1')->text(), 'the first section holds it');

        $crawler = $this->client->request('GET', '/finanzas-claras/sin-formulario');
        self::assertCount(0, $crawler->filter('a.btn'), 'nothing to send people to: no buttons');
        self::assertSelectorExists('#llamado h2', 'the band still says its words');
        self::assertSelectorNotExists('[data-sticky]');
    }

    public function testTheHeroAndTheFormReassureTheVisitor(): void
    {
        $this->createPage($this->createAccount());

        $crawler = $this->client->request('GET', '/finanzas-claras/diagnostico');

        self::assertSelectorTextContains('#portada .eyebrow', 'Diagnóstico financiero gratuito');
        self::assertSame(['Sin costo', '45 minutos', '100 % confidencial'], $crawler->filter('#portada .trust li')->each(static fn ($li) => $li->text()));
        self::assertSame('Da el primer paso hoy', $crawler->filter('#llamado h2')->text());
        self::assertCount(3, $crawler->filter('#formulario .checklist li'));
        self::assertSelectorTextContains('#formulario .safe', 'Finanzas Claras');
        self::assertSelectorExists('link[rel="preload"][href="/fonts/plus-jakarta-sans.woff2"]', 'the font is ours, not a third party\'s');
    }

    public function testAPagePublishedBeforeTheNewFieldsStillShowsWhole(): void
    {
        $this->createPage($this->createAccount(), change: static function (array $content): array {
            // As published before: no band, no eyebrow, trust points or highlights.
            $content['sections'] = array_values(array_filter($content['sections'], static fn (array $s) => 'cta' !== $s['type']));
            foreach ($content['sections'] as &$section) {
                unset($section['fields']['eyebrow'], $section['fields']['trustPoints'], $section['fields']['highlights']);
            }

            return $content;
        });

        $crawler = $this->client->request('GET', '/finanzas-claras/diagnostico');

        self::assertSame(200, $this->responseStatus());
        self::assertSelectorNotExists('.eyebrow');
        self::assertSelectorNotExists('.trust');
        self::assertSelectorNotExists('#llamado');
        self::assertSelectorExists('#formulario form');
        self::assertSame('#formulario', $crawler->filter('#portada a.btn')->attr('href'));
    }

    public function testOnlyWhatIsPublishedIsServed(): void
    {
        $account = $this->createAccount();
        $this->createPage($account, 'borrador', published: false);
        $disabled = $this->createPage($account, 'retirada');
        $this->save($disabled->disable());

        $this->client->request('GET', '/finanzas-claras/borrador');
        self::assertSame(404, $this->responseStatus());
        $this->client->request('GET', '/finanzas-claras/retirada');
        self::assertSame(410, $this->responseStatus(), 'gone: search engines drop it');
        $this->client->request('GET', '/finanzas-claras/nada');
        self::assertSame(404, $this->responseStatus());
    }

    public function testTheHomePageLivesAtTheConsultantsAddressOnly(): void
    {
        $account = $this->createAccount();
        $this->createPage($account, 'inicio', home: true);

        $this->client->request('GET', '/finanzas-claras');
        self::assertSame(200, $this->responseStatus());
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost:8080/finanzas-claras"]');
        self::assertSelectorExists('form[action="/finanzas-claras/enviar"]');
        $this->client->request('GET', '/finanzas-claras/inicio');
        self::assertResponseRedirects('/finanzas-claras', 301);
    }

    public function testAConsultantsFormerAddressRedirectsEveryPath(): void
    {
        $account = $this->createAccount();
        $this->createPage($account, 'diagnostico');
        $this->actAs($this->createSuperAdmin());
        $this->api('PATCH', '/api/platform/accounts/'.$account->getId(), ['slug' => 'plata-clara']);
        $this->signOut();

        $this->client->request('GET', '/finanzas-claras/diagnostico?utm_source=ig');
        self::assertResponseRedirects('/plata-clara/diagnostico?utm_source=ig', 301);
        $this->client->request('GET', '/finanzas-claras');
        self::assertResponseRedirects('/plata-clara', 301);

        // Another consultant cannot take the former address while it still sends visitors on.
        $this->actAs($this->createSuperAdmin('otra@pontiac.test'));
        $error = $this->api('POST', '/api/platform/accounts', ['name' => 'X', 'slug' => 'finanzas-claras', 'ownerName' => 'Y', 'ownerEmail' => 'y@demo.test']);
        self::assertSame('slug', $error['violations'][0]['field']);
    }

    public function testAnUnchangedPageAnswersNotModified(): void
    {
        $this->createPage($this->createAccount());

        $this->client->request('GET', '/finanzas-claras/diagnostico');
        $etag = (string) $this->client->getResponse()->headers->get('ETag');
        self::assertStringContainsString('public', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', '/finanzas-claras/diagnostico', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertSame(304, $this->responseStatus());
    }

    public function testTheSitemapsListWhatSearchEnginesMayIndex(): void
    {
        $account = $this->createAccount();
        $this->createPage($account, 'inicio', home: true);
        $this->createPage($account, 'plan', PageTemplate::PlanOffer);
        $this->createPage($account, 'oculta', change: static function (array $content): array {
            $content['seo']['index'] = false;

            return $content;
        });
        $this->createPage($account, 'borrador', published: false);

        $this->client->request('GET', '/finanzas-claras/sitemap.xml');
        $xml = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<loc>http://localhost:8080/finanzas-claras</loc>', $xml);
        self::assertStringContainsString('<loc>http://localhost:8080/finanzas-claras/plan</loc>', $xml);
        self::assertStringNotContainsString('oculta', $xml);
        self::assertStringNotContainsString('borrador', $xml);

        $this->client->request('GET', '/sitemap.xml');
        self::assertStringContainsString('<loc>http://localhost:8080/finanzas-claras/sitemap.xml</loc>', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', '/robots.txt');
        self::assertStringContainsString('Sitemap: http://localhost:8080/sitemap.xml', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Disallow: /admin', (string) $this->client->getResponse()->getContent());
    }

    public function testThePrivacyPolicyIsTheConsultantsOrThePlatformsDefault(): void
    {
        $account = $this->createAccount();
        $owner = $this->createOwner($account);
        $this->actAs($this->createSuperAdmin());
        $this->api('PATCH', '/api/platform/settings', ['defaultPrivacyText' => 'Texto por defecto de Pontiac.']);
        $this->signOut();

        $this->client->request('GET', '/finanzas-claras/privacidad');
        self::assertSelectorTextContains('main', 'Texto por defecto de Pontiac.');

        $this->actAs($owner);
        $saved = $this->api('PUT', '/api/admin/privacy', ['text' => 'Mi propia política.']);
        self::assertSame(['Mi propia política.', false], [$saved['text'], $saved['usingDefault']]);
        $this->signOut();
        $this->client->request('GET', '/finanzas-claras/privacidad');
        self::assertSelectorTextContains('main', 'Mi propia política.');
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $change  `enabled`, or fields
     *
     * @return array<string, mixed>
     */
    private static function changeSection(array $content, string $id, array $change): array
    {
        foreach ($content['sections'] as &$section) {
            if ($id === $section['id']) {
                if (\array_key_exists('enabled', $change)) {
                    $section['enabled'] = $change['enabled'];
                    unset($change['enabled']);
                }
                $section['fields'] = $change + $section['fields'];
            }
        }

        return $content;
    }

    /** The hero's button, the header's, the band's and the sticky bar's all lead to the same place. */
    private function assertButtonsLeadTo(string $path, string $target, string $why): \Symfony\Component\DomCrawler\Crawler
    {
        $crawler = $this->client->request('GET', $path);
        self::assertSame(200, $this->responseStatus(), $why);
        foreach (['#portada a.btn', 'header.bar a.btn', '#llamado a.btn', '[data-sticky] a.btn'] as $selector) {
            self::assertSame($target, $crawler->filter($selector)->attr('href'), "$why: $selector");
        }

        return $crawler;
    }
}
