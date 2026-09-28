<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Account;
use App\Entity\User;

/**
 * Prospectos and Ajustes › Categorías: the consultant sorts the people who answered, looks at what each sent, and
 * erases a person's data when they ask (Ley 1581).
 */
final class ContactsTest extends ApiTestCase
{
    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->owner = $this->createOwner($this->account);
    }

    public function testCategoriesAreCreatedRenamedAndDisabled(): void
    {
        $this->actAs($this->owner);

        $category = $this->api('POST', '/api/admin/categories', ['name' => ' Deudas ', 'color' => 'rose']);
        self::assertSame(['Deudas', 'rose', true], [$category['name'], $category['color'], $category['active']]);
        self::assertSame(['Deudas urgentes', 'warning'], array_values(array_intersect_key($this->api('PUT', '/api/admin/categories/'.$category['id'], ['name' => 'Deudas urgentes', 'color' => 'warning']), ['name' => 1, 'color' => 1])));

        $error = $this->api('POST', '/api/admin/categories', ['name' => '', 'color' => 'fucsia']);
        self::assertEqualsCanonicalizing(['name', 'color'], array_column($error['violations'], 'field'));

        self::assertFalse($this->api('DELETE', '/api/admin/categories/'.$category['id'])['active']);
        self::assertSame(0, $this->api('GET', '/api/admin/categories')['total']);
        self::assertSame(['Deudas urgentes'], array_column($this->api('GET', '/api/admin/categories/all')['items'], 'name'), 'pickers still show it');
        self::assertTrue($this->api('POST', '/api/admin/categories/'.$category['id'].'/enable')['active']);
    }

    public function testTheListFiltersByCategoryAndPage(): void
    {
        $debts = $this->createCategory($this->account, 'Deudas');
        $this->createPage($this->account, 'uno', change: static function (array $content) use ($debts): array {
            $content['settings']['defaultCategoryId'] = (string) $debts->getId();

            return $content;
        });
        $two = $this->createPage($this->account, 'dos');
        $this->client->request('POST', '/finanzas-claras/uno/enviar', $this->formData('laura@demo.test'));
        $this->client->request('POST', '/finanzas-claras/dos/enviar', $this->formData('carlos@demo.test', ['name' => 'Carlos Ruiz']));
        $this->actAs($this->owner);

        self::assertSame(['Laura Gómez'], array_column($this->api('GET', '/api/admin/contacts?category='.$debts->getId())['items'], 'fullName'));
        self::assertSame(['Carlos Ruiz'], array_column($this->api('GET', '/api/admin/contacts?category=none')['items'], 'fullName'));
        self::assertSame(['Carlos Ruiz'], array_column($this->api('GET', '/api/admin/contacts?sourcePage='.$two->getId())['items'], 'fullName'));
        self::assertSame(2, $this->api('GET', '/api/admin/contacts?status=lead')['total']);
        $this->api('GET', '/api/admin/contacts?status=vip');
        self::assertSame(400, $this->responseStatus());
    }

    public function testTheConsultantChangesAContactsCategory(): void
    {
        $category = $this->createCategory($this->account);
        $this->createPage($this->account);
        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData());
        $this->actAs($this->owner);
        $id = $this->api('GET', '/api/admin/contacts')['items'][0]['id'];

        self::assertSame('Deudas', $this->api('PATCH', '/api/admin/contacts/'.$id, ['categoryId' => (string) $category->getId()])['category']['name']);
        self::assertSame('Deudas', $this->api('PATCH', '/api/admin/contacts/'.$id, [])['category']['name'], 'left out: left as it is');
        self::assertNull($this->api('PATCH', '/api/admin/contacts/'.$id, ['categoryId' => null])['category']);

        $other = $this->createCategory($this->createAccount('Plata Sana'), 'Ajena');
        $this->actAs($this->owner);
        $error = $this->api('PATCH', '/api/admin/contacts/'.$id, ['categoryId' => (string) $other->getId()]);
        self::assertSame('categoryId', $error['violations'][0]['field']);
    }

    public function testOnlyTheOwnerErasesAPersonsDataForGood(): void
    {
        $this->createPage($this->account, change: static function (array $content): array {
            $content['form']['fields'] = [['key' => 'ciudad', 'label' => 'Ciudad', 'type' => 'text', 'required' => false, 'options' => [], 'optionCategories' => []]];

            return $content;
        });
        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData(extra: ['ciudad' => 'Medellín']));
        $assistant = $this->createAssistant($this->account);
        $this->actAs($assistant);
        $id = $this->api('GET', '/api/admin/contacts')['items'][0]['id'];

        $this->api('POST', '/api/admin/contacts/'.$id.'/anonymize');
        self::assertSame(403, $this->responseStatus());

        $this->actAs($this->owner);
        $erased = $this->api('POST', '/api/admin/contacts/'.$id.'/anonymize');
        self::assertSame(['Anonimizado', null, true, []], [$erased['fullName'], $erased['phone'], $erased['anonymized'], $erased['submissions'][0]['answers']]);
        self::assertStringNotContainsString('laura', $erased['email']);
        $this->api('POST', '/api/admin/contacts/'.$id.'/anonymize');
        self::assertSame(409, $this->responseStatus());

        // The same person may answer again later: a new contact, since nothing links them to the erased one.
        $this->signOut();
        $this->client->request('POST', '/finanzas-claras/diagnostico/enviar', $this->formData());
        $this->actAs($this->owner);
        self::assertSame(2, $this->api('GET', '/api/admin/contacts')['total']);
    }

    public function testAnotherConsultantsContactsAndCategoriesAreNotFound(): void
    {
        $other = $this->createAccount('Plata Sana');
        $theirCategory = $this->createCategory($other);
        $this->createPage($other);
        $this->client->request('POST', '/plata-sana/diagnostico/enviar', $this->formData());
        $this->actAs($this->createOwner($other, 'otro@demo.test'));
        $theirContact = $this->api('GET', '/api/admin/contacts')['items'][0]['id'];
        $this->actAs($this->owner);

        foreach ([['GET', '/api/admin/contacts/'.$theirContact], ['PATCH', '/api/admin/contacts/'.$theirContact], ['POST', '/api/admin/contacts/'.$theirContact.'/anonymize'], ['PUT', '/api/admin/categories/'.$theirCategory->getId()], ['DELETE', '/api/admin/categories/'.$theirCategory->getId()]] as [$method, $path]) {
            $this->api($method, $path, 'GET' === $method || 'DELETE' === $method ? null : ['name' => 'x', 'color' => 'info']);
            self::assertSame(404, $this->responseStatus(), $method.' '.$path);
        }
        self::assertSame(0, $this->api('GET', '/api/admin/contacts')['total']);
    }
}
