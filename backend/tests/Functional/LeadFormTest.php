<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Contact;
use App\Entity\LeadSubmission;
use App\Enum\PageTemplate;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Component\Mime\Email;

/**
 * A visitor sends a page's form: they become a prospecto (or add to the one they are), sorted into a category, and the
 * consultant hears about it. Mistakes come back in Spanish with what they typed; scripts get nowhere.
 */
final class LeadFormTest extends ApiTestCase
{
    public function testAPersonBecomesAProspectoSortedByTheirAnswer(): void
    {
        $account = $this->createAccount();
        $owner = $this->createOwner($account);
        $debts = $this->createCategory($account, 'Deudas');
        $general = $this->createCategory($account, 'General', 'info');
        $page = $this->createPage($account, change: static function (array $content) use ($debts, $general): array {
            $content['settings']['defaultCategoryId'] = (string) $general->getId();
            $content['form']['fields'] = [
                ['key' => 'preocupacion', 'label' => '¿Qué te preocupa?', 'type' => 'select', 'required' => true, 'options' => ['Mis deudas', 'Otra cosa'], 'optionCategories' => ['Mis deudas' => (string) $debts->getId()]],
                ['key' => 'noticias', 'label' => 'Quiero recibir novedades', 'type' => 'checkbox', 'required' => false, 'options' => [], 'optionCategories' => []],
            ];

            return $content;
        });

        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData(extra: ['preocupacion' => 'Mis deudas']), server: ['HTTP_REFERER' => 'http://localhost/finanzas-claras/diagnostico?utm_source=instagram&utm_campaign=octubre']);

        self::assertResponseRedirects('/finanzas-claras/diagnostico?enviado=1#formulario', 303);
        // The consultant hears about it, from the queue.
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Nuevo prospecto: Laura Gómez', $email->getSubject());
        self::assertEmailAddressContains($email, 'to', 'asesor@demo.test');
        self::assertEmailAddressContains($email, 'reply-to', 'laura@demo.test');

        $this->actAs($owner);
        $contacts = $this->api('GET', '/api/admin/contacts')['items'];
        self::assertSame([['Laura Gómez', 'laura@demo.test', '300 123 4567', 'lead', 'Deudas', 'diagnostico']], array_map(static fn (array $c) => [$c['fullName'], $c['email'], $c['phone'], $c['status'], $c['category']['name'], $c['sourcePage']['slug']], $contacts));
        $detail = $this->api('GET', '/api/admin/contacts/'.$contacts[0]['id']);
        self::assertSame([['key' => 'preocupacion', 'label' => '¿Qué te preocupa?', 'value' => 'Mis deudas'], ['key' => 'noticias', 'label' => 'Quiero recibir novedades', 'value' => 'No']], $detail['submissions'][0]['answers']);
        self::assertSame([['name' => 'utm_source', 'value' => 'instagram'], ['name' => 'utm_campaign', 'value' => 'octubre']], $detail['submissions'][0]['utm']);
        self::assertSame((string) $page->getId(), $detail['submissions'][0]['page']['id']);

    }

    public function testThePagesDefaultCategoryAppliesWhenNoAnswerSaysOtherwise(): void
    {
        $account = $this->createAccount();
        $general = $this->createCategory($account, 'General', 'info');
        $this->createPage($account, change: static function (array $content) use ($general): array {
            $content['settings']['defaultCategoryId'] = (string) $general->getId();

            return $content;
        });

        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData());

        self::assertSame('General', $this->contact('laura@demo.test')->getCategory()?->getName());
    }

    public function testTheSamePersonAgainIsTheSameContactWithAnotherSubmission(): void
    {
        $account = $this->createAccount();
        $this->createPage($account, 'uno');
        $this->createPage($account, 'dos', PageTemplate::PlanOffer);

        $this->client->request('POST', '/finanzas-claras/uno/enviar', $this->formData());
        $this->client->request('POST', '/finanzas-claras/dos/enviar', $this->formData('LAURA@demo.test', ['name' => 'Laura G.', 'phone' => '']));

        $contact = $this->contact('laura@demo.test');
        self::assertSame(['Laura G.', '300 123 4567', 'uno'], [$contact->getFullName(), $contact->getPhone(), $contact->getSourcePage()?->getSlug()], 'the last name they gave; a blank phone keeps the one they had; the page that brought them first');
        self::assertSame(2, $this->em()->getRepository(LeadSubmission::class)->count(['contact' => $contact]));
    }

    public function testMistakesComeBackInSpanishWithWhatWasTyped(): void
    {
        $this->createPage($this->createAccount(), change: static function (array $content): array {
            $content['form']['fields'] = [['key' => 'ciudad', 'label' => 'Ciudad', 'type' => 'text', 'required' => true, 'options' => [], 'optionCategories' => []]];

            return $content;
        });

        $crawler = $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData(extra: ['email' => 'no-es-correo', 'phone' => '12', 'consent' => '', 'ciudad' => '']));

        self::assertSame(422, $this->responseStatus());
        $errors = $crawler->filter('.error')->each(static fn ($node) => $node->text());
        self::assertSame([
            'Escribe un correo válido, por ejemplo nombre@correo.com.',
            'Escribe un teléfono de 7 a 15 dígitos.',
            'Completa este campo.',
            'Para enviar tus datos debes aceptar la política de privacidad.',
        ], $errors);
        self::assertSame('Laura Gómez', $crawler->filter('#f-name')->attr('value'), 'what they typed stays');
        $this->asPlatform();
        self::assertSame(0, $this->em()->getRepository(Contact::class)->count([]));
    }

    public function testReloadingTheFormsAddressGoesBackToThePage(): void
    {
        $this->createPage($this->createAccount());

        $this->client->request('GET', '/finanzas-claras/diagnostico/enviar');
        self::assertResponseRedirects('/finanzas-claras/diagnostico#formulario', 303);
        $this->client->request('GET', '/finanzas-claras/enviar');
        self::assertResponseRedirects('/finanzas-claras#formulario', 303);
    }

    public function testAScriptGetsNowhere(): void
    {
        $this->createPage($this->createAccount());

        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData(extra: ['website' => 'http://spam.test']));
        self::assertResponseRedirects(null, 303, 'the trap field filled: it looks sent');
        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData(extra: ['_t' => (string) time()]));
        self::assertResponseRedirects(null, 303, 'sent back instantly');
        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData(extra: ['_t' => '123.forged']));

        $this->asPlatform();
        self::assertSame(0, $this->em()->getRepository(Contact::class)->count([]));
        self::assertQueuedEmailCount(0);
    }

    public function testAfterSendingThePageSaysSo(): void
    {
        $this->createPage($this->createAccount());

        $this->client->request('GET', '/finanzas-claras/diagnostico?enviado=1');

        self::assertSelectorTextContains('#formulario [role="status"]', '¡Gracias! Te escribiremos muy pronto');
        self::assertSelectorNotExists('#formulario form');
    }

    public function testALeadMagnetEmailsTheResource(): void
    {
        $this->createPage($this->createAccount(), 'plantilla', PageTemplate::LeadMagnet, change: static function (array $content): array {
            $content['sections'][3]['fields']['resourceUrl'] = 'https://example.com/plantilla.xlsx';

            return $content;
        });

        $this->client->request('POST', '/finanzas-claras/plantilla/enviar', $this->formData());

        $toVisitor = array_values(array_filter(self::getMailerMessages(), static fn ($m) => $m instanceof Email && 'laura@demo.test' === $m->getTo()[0]->getAddress()));
        self::assertCount(1, $toVisitor);
        self::assertEmailHtmlBodyContains($toVisitor[0], 'https://example.com/plantilla.xlsx');
        self::assertSame('Finanzas Claras', $toVisitor[0]->getFrom()[0]->getName());
    }

    public function testNothingIsReceivedOnAPageThatIsNotLive(): void
    {
        $this->createPage($this->createAccount(), 'borrador', published: false);

        $this->client->request('POST', '/finanzas-claras/borrador/enviar', $this->formData());

        self::assertSame(404, $this->responseStatus());
    }

    private function contact(string $email): Contact
    {
        $this->em()->clear();
        $this->asPlatform();

        return $this->em()->getRepository(Contact::class)->findOneBy(['email' => $email]) ?? throw new \LogicException('No contact '.$email);
    }
}
