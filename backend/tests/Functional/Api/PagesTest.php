<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Account;
use App\Entity\User;
use App\Enum\PageTemplate;

/**
 * Páginas: a consultant creates pages from the templates the platform offers, edits the draft (checked against the
 * template), previews it, publishes within their page limit, and takes pages down. Visitors see only what was
 * published.
 */
final class PagesTest extends ApiTestCase
{
    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->owner = $this->createOwner($this->account);
    }

    public function testANewPageIsTheTemplateReadyToEditAndTheFirstIsHome(): void
    {
        $this->actAs($this->owner);

        $page = $this->api('POST', '/api/admin/pages', ['title' => 'Diagnóstico', 'slug' => 'Diagnostico', 'template' => 'free_diagnostic']);

        self::assertSame(201, $this->responseStatus());
        self::assertSame(['diagnostico', 'draft', true, '/finanzas-claras', false], [$page['slug'], $page['status'], $page['home'], $page['path'], null !== $page['publishedAt']]);
        self::assertSame(['portada', 'problema', 'como-funciona', 'reserva', 'preguntas', 'formulario'], array_column($page['draft']['sections'], 'id'));
        self::assertFalse($page['draft']['sections'][3]['enabled'], 'booking waits for a free plan');
        self::assertSame('Diagnóstico', $page['draft']['seo']['title']);

        $second = $this->api('POST', '/api/admin/pages', ['title' => 'Plan', 'slug' => 'plan', 'template' => 'plan_offer']);
        self::assertFalse($second['home']);
        self::assertSame('/finanzas-claras/plan', $second['path']);
    }

    public function testEveryTemplatesDefaultContentIsValidAndPublishable(): void
    {
        $this->actAs($this->owner);

        foreach (PageTemplate::cases() as $template) {
            $page = $this->api('POST', '/api/admin/pages', ['title' => $template->value, 'slug' => str_replace('_', '-', $template->value), 'template' => $template->value]);
            $saved = $this->api('PATCH', '/api/admin/pages/'.$page['id'], ['draft' => $page['draft']]);
            self::assertSame(200, $this->responseStatus(), $template->value);
            self::assertSame($page['draft'], $saved['draft'], $template->value.': the defaults come back as they went');
        }
    }

    public function testAnAddressMustBeWellFormedFreeAndNotReserved(): void
    {
        $this->createPage($this->account, 'diagnostico');
        $this->actAs($this->owner);

        foreach (['diagnostico' => 'Another of your pages already uses this address.', 'portal' => 'This address is reserved.', 'privacidad' => 'This address is reserved.', 'Con Espacios' => 'Use 3 to 60 lowercase letters, numbers and hyphens.'] as $slug => $message) {
            $error = $this->api('POST', '/api/admin/pages', ['title' => 'X', 'slug' => $slug, 'template' => 'plan_offer']);
            self::assertSame(422, $this->responseStatus(), $slug);
            self::assertSame([['field' => 'slug', 'message' => $message]], $error['violations'], $slug);
        }
    }

    public function testOnlyTheTemplatesThePlatformOffersCanBeUsed(): void
    {
        $this->actAs($this->createSuperAdmin());
        $this->api('PATCH', '/api/platform/settings', ['enabledTemplates' => ['free_diagnostic']]);
        $this->actAs($this->owner);

        $error = $this->api('POST', '/api/admin/pages', ['title' => 'Evento', 'slug' => 'evento', 'template' => 'event']);
        self::assertSame(422, $this->responseStatus());
        self::assertSame('template', $error['violations'][0]['field']);
        self::assertFalse($this->api('GET', '/api/admin/pages/catalog')['templates'][3]['enabled']);
    }

    public function testADraftIsCheckedAgainstItsTemplateAndEveryProblemIsReportedWhereItIs(): void
    {
        $page = $this->createPage($this->account, published: false);
        $this->actAs($this->owner);
        $draft = $page->getDraft();
        $draft['sections'][0]['fields']['heading'] = '';
        $draft['sections'][0]['fields']['image'] = '0192f1e0-0000-7000-8000-000000000000';
        $draft['sections'][2]['fields']['items'][0]['title'] = str_repeat('x', 81);
        $draft['form']['fields'] = [['label' => 'Ciudad', 'type' => 'select', 'options' => [], 'required' => true], ['label' => '', 'type' => 'radio']];
        $draft['seo']['title'] = '';
        $draft['settings']['accent'] = 'fucsia';
        $draft['settings']['defaultCategoryId'] = (string) $this->createCategory($this->createAccount('Plata Sana'))->getId();

        $error = $this->api('PATCH', '/api/admin/pages/'.$page->getId(), ['draft' => $draft]);

        self::assertSame(422, $this->responseStatus());
        self::assertEqualsCanonicalizing([
            'sections[0].fields.heading',
            'sections[0].fields.image',
            'sections[2].fields.items[0].title',
            'form.fields[0].options',
            'form.fields[1].label',
            'form.fields[1].type',
            'seo.title',
            'settings.accent',
            'settings.defaultCategoryId',
        ], array_column($error['violations'], 'field'), 'an image or category of another consultant is refused like a missing one');
    }

    public function testSectionsTurnOffAndReorderButOnlyTheTemplatesExist(): void
    {
        $page = $this->createPage($this->account, published: false);
        $this->actAs($this->owner);
        $draft = $page->getDraft();

        $draft['sections'] = array_reverse($draft['sections']);
        $draft['sections'][1]['enabled'] = false;
        $draft['sections'][1]['fields']['items'] = [['question' => '', 'answer' => '']];
        $saved = $this->api('PATCH', '/api/admin/pages/'.$page->getId(), ['draft' => $draft]);
        self::assertSame(200, $this->responseStatus(), 'a section switched off may be left half written');
        self::assertSame(['formulario', 'preguntas', 'reserva', 'como-funciona', 'problema', 'portada'], array_column($saved['draft']['sections'], 'id'));

        $draft['sections'][] = ['id' => 'extra', 'type' => 'text', 'enabled' => true, 'fields' => []];
        $error = $this->api('PATCH', '/api/admin/pages/'.$page->getId(), ['draft' => $draft]);
        self::assertSame('sections', $error['violations'][0]['field'], 'a section the template does not have');
    }

    public function testADraftFromBeforeATemplateGainedASectionGetsItSwitchedOff(): void
    {
        $page = $this->createPage($this->account, published: false, change: static function (array $content): array {
            // As a page made before the booking section existed.
            $content['sections'] = array_values(array_filter($content['sections'], static fn (array $s) => 'reserva' !== $s['id']));

            return $content;
        });
        $this->actAs($this->owner);

        $saved = $this->api('PATCH', '/api/admin/pages/'.$page->getId(), ['draft' => $page->getDraft()]);

        self::assertSame(200, $this->responseStatus());
        self::assertSame(['reserva', false], [$saved['draft']['sections'][5]['id'], $saved['draft']['sections'][5]['enabled']]);
    }

    public function testExtraFormFieldsGetStableKeys(): void
    {
        $page = $this->createPage($this->account, published: false);
        $category = $this->createCategory($this->account);
        $this->actAs($this->owner);
        $draft = $page->getDraft();
        $draft['form']['fields'] = [
            ['label' => '¿Cuál es tu mayor preocupación?', 'type' => 'select', 'required' => true, 'options' => ['Deudas', 'Ahorro'], 'optionCategories' => ['Deudas' => (string) $category->getId(), 'Otra' => (string) $category->getId()]],
            ['label' => 'Email', 'type' => 'text'],
            ['key' => 'ciudad_actual', 'label' => 'Ciudad', 'type' => 'text'],
        ];

        $fields = $this->api('PATCH', '/api/admin/pages/'.$page->getId(), ['draft' => $draft])['draft']['form']['fields'];

        self::assertSame(['cual_es_tu_mayor_preocupacion', 'campo_2', 'ciudad_actual'], array_column($fields, 'key'), 'a fixed key (email) is never taken; a key once given is kept');
        self::assertSame(['Deudas' => (string) $category->getId()], $fields[0]['optionCategories'], 'only real options map to a category');
    }

    public function testThePreviewIsTheUnsavedDraftAsVisitorsWouldSeeItNeverIndexed(): void
    {
        $page = $this->createPage($this->account);
        $this->actAs($this->owner);
        $draft = $page->getDraft();
        $draft['sections'][0]['fields']['heading'] = 'Un título que aún no guardé';

        $this->client->jsonRequest('POST', '/api/admin/pages/'.$page->getId().'/preview', ['draft' => $draft]);

        self::assertSame(200, $this->responseStatus());
        self::assertSelectorTextContains('h1', 'Un título que aún no guardé');
        self::assertSelectorExists('meta[name="robots"][content="noindex"]');
        self::assertSame('noindex', $this->client->getResponse()->headers->get('X-Robots-Tag'));
        self::assertStringNotContainsString('Un título que aún no guardé', (string) $this->client->request('GET', '/finanzas-claras/diagnostico')->html(), 'nothing was saved');
    }

    public function testPublishingRespectsThePageLimitAndALeadMagnetNeedsItsLink(): void
    {
        $this->save($this->account->setLimits(1, 3, 1024, 10));
        $this->createPage($this->account, 'ya-publicada');
        $draft = $this->createPage($this->account, 'recurso', PageTemplate::LeadMagnet, published: false);
        $this->actAs($this->owner);

        $error = $this->api('POST', '/api/admin/pages/'.$draft->getId().'/publish');
        self::assertSame(422, $this->responseStatus());
        self::assertSame('sections[2].fields.resourceUrl', $error['violations'][0]['field']);

        $content = $draft->getDraft();
        $content['sections'][2]['fields']['resourceUrl'] = 'https://example.com/plantilla.xlsx';
        $this->api('PATCH', '/api/admin/pages/'.$draft->getId(), ['draft' => $content]);
        $error = $this->api('POST', '/api/admin/pages/'.$draft->getId().'/publish');
        self::assertSame(409, $this->responseStatus());
        self::assertSame('page_limit_reached', $error['error']);
    }

    public function testPublishingMakesTheDraftLiveAndLaterEditsWaitForTheNextPublish(): void
    {
        $page = $this->createPage($this->account, published: false);
        $this->actAs($this->owner);

        $published = $this->api('POST', '/api/admin/pages/'.$page->getId().'/publish');
        self::assertSame(['published', false], [$published['status'], $published['hasUnpublishedChanges']]);

        $draft = $published['draft'];
        $draft['sections'][0]['fields']['heading'] = 'Nuevo título';
        self::assertTrue($this->api('PATCH', '/api/admin/pages/'.$page->getId(), ['draft' => $draft])['hasUnpublishedChanges']);
        $this->signOut();
        $this->client->request('GET', '/finanzas-claras/diagnostico');
        self::assertSelectorTextNotContains('h1', 'Nuevo título');
    }

    public function testOnlyTheOwnerTakesAPageDownAndBringsItBack(): void
    {
        $page = $this->createPage($this->account);
        $this->actAs($this->createAssistant($this->account));
        $this->api('POST', '/api/admin/pages/'.$page->getId().'/disable');
        self::assertSame(403, $this->responseStatus());

        $this->actAs($this->owner);
        self::assertSame('disabled', $this->api('POST', '/api/admin/pages/'.$page->getId().'/disable')['status']);
        $this->client->request('GET', '/finanzas-claras/diagnostico');
        self::assertSame(410, $this->responseStatus());
        self::assertSame('published', $this->api('POST', '/api/admin/pages/'.$page->getId().'/reactivate')['status']);
    }

    public function testADuplicateIsADraftCopyAtAFreeAddress(): void
    {
        $page = $this->createPage($this->account, home: true);
        $this->actAs($this->owner);

        $copy = $this->api('POST', '/api/admin/pages/'.$page->getId().'/duplicate');
        $again = $this->api('POST', '/api/admin/pages/'.$page->getId().'/duplicate');

        self::assertSame(['diagnostico-copia', 'draft', false, 'Página diagnostico (copia)'], [$copy['slug'], $copy['status'], $copy['home'], $copy['title']]);
        self::assertSame('diagnostico-copia-2', $again['slug']);
        self::assertEquals($page->getDraft(), $copy['draft'], 'the same content (the database may order its keys differently)');
    }

    public function testThereIsOneHomePage(): void
    {
        $first = $this->createPage($this->account, 'uno', home: true);
        $second = $this->createPage($this->account, 'dos');
        $this->actAs($this->owner);

        self::assertTrue($this->api('POST', '/api/admin/pages/'.$second->getId().'/home')['home']);
        self::assertFalse($this->api('GET', '/api/admin/pages/'.$first->getId())['home']);
        $this->signOut();
        $this->client->request('GET', '/finanzas-claras');
        self::assertSelectorTextContains('title', 'Página dos');
    }

    public function testAPublishedPageThatChangesAddressLeavesARedirect(): void
    {
        $page = $this->createPage($this->account, 'diagnostico');
        $this->actAs($this->owner);

        self::assertSame('/finanzas-claras/diagnostico-gratis', $this->api('PATCH', '/api/admin/pages/'.$page->getId(), ['slug' => 'diagnostico-gratis'])['path']);
        $this->signOut();
        $this->client->request('GET', '/finanzas-claras/diagnostico');
        self::assertResponseRedirects('/finanzas-claras/diagnostico-gratis', 301);

        // A new page may take the old address: it stops redirecting.
        $this->actAs($this->owner);
        $this->api('POST', '/api/admin/pages', ['title' => 'Nueva', 'slug' => 'diagnostico', 'template' => 'plan_offer']);
        self::assertSame(201, $this->responseStatus());
    }

    public function testTheListCountsEachPagesLeadsAndFilters(): void
    {
        $page = $this->createPage($this->account, 'diagnostico');
        $this->createPage($this->account, 'plan', PageTemplate::PlanOffer, published: false);
        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData());
        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData('otra@demo.test'));
        $this->actAs($this->owner);

        $list = $this->api('GET', '/api/admin/pages');
        self::assertSame(['plan' => 0, 'diagnostico' => 2], array_column($list['items'], 'leadsLast30Days', 'slug'), 'the most recently changed first');
        self::assertSame(['diagnostico'], array_column($this->api('GET', '/api/admin/pages?status=published')['items'], 'slug'));
        self::assertSame(['plan'], array_column($this->api('GET', '/api/admin/pages?template=plan_offer')['items'], 'slug'));
        $this->api('GET', '/api/admin/pages?status=live');
        self::assertSame(400, $this->responseStatus());
        self::assertSame((string) $page->getId(), $list['items'][1]['id']);
    }

    public function testAnotherConsultantsPagesAreNotFound(): void
    {
        $theirs = $this->createPage($this->createAccount('Plata Sana'));
        $this->actAs($this->owner);

        foreach ([['GET', ''], ['PATCH', ''], ['POST', '/publish'], ['POST', '/duplicate'], ['POST', '/preview'], ['POST', '/home']] as [$method, $suffix]) {
            $this->api($method, '/api/admin/pages/'.$theirs->getId().$suffix, 'GET' === $method ? null : []);
            self::assertSame(404, $this->responseStatus(), $method.$suffix);
        }
        self::assertSame(0, $this->api('GET', '/api/admin/pages')['total']);
    }
}
