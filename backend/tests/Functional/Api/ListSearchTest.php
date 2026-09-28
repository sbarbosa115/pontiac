<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Account;
use App\Entity\EmailTemplate;
use App\Entity\Enrollment;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\PageTemplate;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every table in the UI has a search box, so every list endpoint behind one takes ?q=. Add every new list here.
 *
 * The term is matched case-insensitively against the fields the search box's placeholder names, an empty term
 * changes nothing, and a term nobody matches returns an empty page rather than everything.
 */
final class ListSearchTest extends ApiTestCase
{
    private User $owner;
    private User $superAdmin;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->createAccount('Finanzas Claras');
        $this->owner = $this->createOwner($this->account, 'andres@demo.test', 'Andrés Asesor');
        $this->createAssistant($this->account, 'sofia@asistentes.test', 'Sofía Ruiz');
        $this->createOwner($this->createAccount('Plata Sana'), 'paola@plata.test', 'Paola Pérez');
        $this->superAdmin = $this->createSuperAdmin();
        $this->withPassword(User::createSuperAdmin('olga@operaciones.test', 'Olga Operaciones'));
        // Two pages and two categories of the consultant.
        $this->createPage($this->account, 'diagnostico');
        $this->createPage($this->account, 'plan-ahorro', PageTemplate::PlanOffer);
        $this->createCategory($this->account, 'Deudas');
        $this->createCategory($this->account, 'Pensión', 'indigo');
        // Two plans, and two sessions with two people.
        $diagnostic = $this->createPlan($this->account, 'Diagnóstico');
        $this->save($diagnostic->change('Diagnóstico', 'Primera sesión gratuita', '0', 1, 45));
        $this->save($this->createPlan($this->account, 'Plan A', '250000')->change('Plan A', 'Dos sesiones de seguimiento', '250000', 2, 60));
        $marta = $this->createContact($this->account, 'Marta Díaz', 'marta@citas.test');
        $jorge = $this->createContact($this->account, 'Jorge Peña', 'jorge@agenda.test');
        $this->bookSession($marta, $diagnostic, new \DateTimeImmutable('+3 days'));
        $this->bookSession($jorge, $diagnostic, new \DateTimeImmutable('+4 days'));
        // Two payments, by the same two people.
        $planB = $this->createPlan($this->account, 'Plan B', '100000');
        foreach ([$marta, $jorge] as $person) {
            $enrollment = new Enrollment($person, $planB, null);
            $this->save($enrollment, Payment::manual($enrollment, 'cash', null, $this->owner, new \DateTimeImmutable()));
        }

        // Two flows and two flow emails.
        $this->createFlow($this->account, 'Diagnóstico');
        $this->createFlow($this->account, 'Renovación');
        $this->save(new EmailTemplate($this->account, 'Bienvenida', 'Hola {nombre}', 'Gracias por escribir.'));
        $this->save(new EmailTemplate($this->account, 'Seguimiento', 'Cómo vas con tu plan', 'Cuéntanos.'));

        // Two settings changes by two people, and two emails for two consultants.
        $this->actAs($this->superAdmin);
        $this->api('PATCH', '/api/platform/settings', ['senderName' => 'Equipo']);
        $this->api('PATCH', '/api/platform/settings', ['minNoticeHours' => 3]);
        $this->actAs($this->owner);
        $this->api('POST', '/api/admin/team', ['fullName' => 'Nueva', 'email' => 'nueva@demo.test']);
        $this->actAs($this->superAdmin);
        $this->api('POST', '/api/platform/emails/test', ['to' => 'prueba@operaciones.test']);

        // Two images, two contacts of the consultant.
        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData('laura@demo.test'));
        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData('carlos@demo.test', ['name' => 'Carlos Ruiz', 'phone' => '310 555 0000']));
        $this->actAs($this->owner);
        $this->upload('/api/admin/media', ['altText' => 'Retrato del asesor'], ['file' => $this->imageFile(800, 600, 'retrato.jpg')]);
        $this->upload('/api/admin/media', [], ['file' => $this->imageFile(800, 600, 'oficina.jpg')]);
    }

    /**
     * @return iterable<string, array{string, string, string, string}> [who, path, a term matching one row, a term matching another]
     */
    public static function lists(): iterable
    {
        yield 'team by name' => ['owner', '/api/admin/team', 'Andrés', 'Ruiz'];
        yield 'team by email' => ['owner', '/api/admin/team', 'andres@', 'asistentes.test'];
        yield 'consultants by name' => ['superAdmin', '/api/platform/accounts', 'Claras', 'Plata'];
        yield 'consultants by address' => ['superAdmin', '/api/platform/accounts', 'finanzas-', 'plata-sana'];
        yield 'consultants by owner email' => ['superAdmin', '/api/platform/accounts', 'andres@', 'plata.test'];
        yield 'a consultant\'s team' => ['superAdmin', '/api/platform/accounts/{account}/users', 'Andrés', 'Ruiz'];
        yield 'administrators' => ['superAdmin', '/api/platform/admins', 'Paula', 'operaciones.test'];
        yield 'settings history by field' => ['superAdmin', '/api/platform/settings/history', 'senderName', 'minNotice'];
        yield 'emails by recipient' => ['superAdmin', '/api/platform/emails', 'nueva@', 'prueba@'];
        yield 'emails by consultant' => ['superAdmin', '/api/platform/emails', 'Finanzas', 'correo de prueba'];
        yield 'pages by title' => ['owner', '/api/admin/pages', 'Página diagnostico', 'Página plan'];
        yield 'pages by address' => ['owner', '/api/admin/pages', 'diagnostico', 'plan-ahorro'];
        yield 'categories' => ['owner', '/api/admin/categories', 'Deudas', 'Pensión'];
        yield 'images by name' => ['owner', '/api/admin/media', 'oficina', 'retrato.jpg'];
        yield 'images by alt text' => ['owner', '/api/admin/media', 'Retrato del', 'oficina.jpg'];
        yield 'contacts by name' => ['owner', '/api/admin/contacts', 'Laura', 'Carlos'];
        yield 'contacts by email' => ['owner', '/api/admin/contacts', 'laura@', 'carlos@'];
        yield 'contacts by phone' => ['owner', '/api/admin/contacts', '300 123', '310 555'];
        yield 'plans by name' => ['owner', '/api/admin/plans', 'Diagnóstico', 'Plan A'];
        yield 'plans by description' => ['owner', '/api/admin/plans', 'gratuita', 'Dos sesiones'];
        yield 'sessions by contact name' => ['owner', '/api/admin/sessions', 'Marta', 'Jorge'];
        yield 'sessions by contact email' => ['owner', '/api/admin/sessions', 'citas.test', 'jorge@'];
        yield 'payments by person' => ['owner', '/api/admin/payments', 'Marta', 'Peña'];
        yield 'payments by email' => ['owner', '/api/admin/payments', 'marta@', 'agenda.test'];
        yield 'flows by name' => ['owner', '/api/admin/flows', 'Diagnóstico', 'Renovación'];
        yield 'flow emails by name' => ['owner', '/api/admin/email-templates', 'Bienvenida', 'Seguimiento'];
        yield 'flow emails by subject' => ['owner', '/api/admin/email-templates', 'Hola {', 'Cómo vas'];
    }

    #[DataProvider('lists')]
    public function testEveryListNarrowsToTheSearchTerm(string $who, string $path, string $matching, string $other): void
    {
        $this->actAs($this->{$who});
        $path = str_replace('{account}', (string) $this->account->getId(), $path);

        $all = $this->api('GET', $path)['total'];
        self::assertGreaterThan(1, $all, 'the fixture needs more than one row for the filter to prove anything');
        self::assertSame($all, $this->api('GET', $path.'?q=')['total'], 'an empty term filters nothing');
        self::assertSame(1, $this->api('GET', $path.'?q='.urlencode($matching))['total'], sprintf('"%s" matches exactly one row of %s', $matching, $path));
        self::assertSame(1, $this->api('GET', $path.'?q='.urlencode(mb_strtoupper($matching)))['total'], 'the search ignores case');
        self::assertSame(1, $this->api('GET', $path.'?q='.urlencode($other))['total'], 'and the other row is findable too');
        self::assertSame(0, $this->api('GET', $path.'?q=zzz-nobody')['total'], 'no match is an empty page, not everything');
    }

    public function testAWildcardInTheTermIsTakenLiterally(): void
    {
        $this->actAs($this->owner);

        // "%" and "_" would match every row if the term were pasted into the LIKE pattern unescaped.
        self::assertSame(0, $this->api('GET', '/api/admin/team?q='.urlencode('%'))['total']);
        self::assertSame(0, $this->api('GET', '/api/admin/team?q='.urlencode('_'))['total']);
    }
}
