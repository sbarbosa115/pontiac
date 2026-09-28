<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use App\Entity\Account;
use App\Entity\User;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Component\Mime\Email;

/**
 * Plataforma › Asesores: a super admin creates consultants (the owner is invited by email), changes their data,
 * limits and features, suspends and reactivates them, and looks after their owner and assistants.
 */
final class AccountsTest extends ApiTestCase
{
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = $this->createSuperAdmin();
    }

    public function testCreatingAConsultantInvitesItsOwnerAndCopiesTheDefaults(): void
    {
        $this->actAs($this->superAdmin);
        $this->api('PATCH', '/api/platform/settings', ['defaultMaxAssistants' => 1, 'defaultFeatures' => ['booking', 'portal']]);

        $created = $this->api('POST', '/api/platform/accounts', [
            'name' => ' Plata Sana ',
            'slug' => 'Plata-Sana',
            'ownerName' => 'Paola Pérez',
            'ownerEmail' => 'Paola@Demo.test',
        ]);

        self::assertSame(201, $this->responseStatus());
        self::assertSame(['Plata Sana', 'plata-sana', true, 1, ['booking', 'portal']], [$created['name'], $created['slug'], $created['active'], $created['maxAssistants'], $created['features']]);
        self::assertSame(['CO', 'COP', 'es_CO', 'America/Bogota'], [$created['country'], $created['currency'], $created['locale'], $created['timezone']]);
        self::assertSame(['fullName' => 'Paola Pérez', 'email' => 'paola@demo.test', 'loginStatus' => 'invited'], array_intersect_key($created['owner'], array_flip(['fullName', 'email', 'loginStatus'])));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'to', 'paola@demo.test');
        self::assertEmailHtmlBodyContains($email, 'Tu cuenta de asesor en Pontiac está lista');
        self::assertSame('Tu cuenta de asesor en Pontiac está lista', $email->getSubject());

        // The defaults were copied: changing them now leaves this consultant alone.
        $this->api('PATCH', '/api/platform/settings', ['defaultMaxAssistants' => 5]);
        self::assertSame(1, $this->api('GET', '/api/platform/accounts/'.$created['id'])['maxAssistants']);
    }

    public function testAnAddressMustBeWellFormedFreeAndNotReserved(): void
    {
        $this->createAccount('Finanzas Claras');
        $this->actAs($this->superAdmin);
        $this->api('PATCH', '/api/platform/settings', ['reservedSlugs' => ['pontiac-demo']]);

        $cases = [
            'taken' => ['finanzas-claras', 'Esa dirección ya la usa otro asesor.'],
            'an app path' => ['admin', 'Esa dirección está reservada.'],
            'reserved in the settings' => ['pontiac-demo', 'Esa dirección está reservada.'],
            'malformed' => ['Plata Sana!', 'Usa de 3 a 60 letras minúsculas, números y guiones.'],
            'too short' => ['ab', 'Usa de 3 a 60 letras minúsculas, números y guiones.'],
        ];
        foreach ($cases as $case => [$slug, $message]) {
            $this->client->jsonRequest('POST', '/api/platform/accounts', ['name' => 'X', 'slug' => $slug, 'ownerName' => 'Y', 'ownerEmail' => 'y@demo.test'], ['HTTP_ACCEPT_LANGUAGE' => 'es']);
            self::assertSame(422, $this->responseStatus(), $case);
            $error = json_decode((string) $this->client->getResponse()->getContent(), true);
            self::assertSame([['field' => 'slug', 'message' => $message]], $error['violations'], $case);
        }
        self::assertEmailCount(0);
    }

    public function testAnOwnerEmailAlreadyOnPontiacLeavesNoConsultantBehind(): void
    {
        $this->createOwner($this->createAccount('Finanzas Claras'), 'ocupado@demo.test');
        $this->actAs($this->superAdmin);

        $error = $this->api('POST', '/api/platform/accounts', ['name' => 'Plata Sana', 'slug' => 'plata-sana', 'ownerName' => 'Y', 'ownerEmail' => 'ocupado@demo.test']);

        self::assertSame(409, $this->responseStatus());
        self::assertSame('email_in_use', $error['error']);
        self::assertSame(0, $this->api('GET', '/api/platform/accounts?q=plata')['total']);
    }

    public function testCreatingValidatesEveryField(): void
    {
        $this->actAs($this->superAdmin);

        $error = $this->api('POST', '/api/platform/accounts', ['ownerEmail' => 'no-es-un-correo']);

        self::assertSame(422, $this->responseStatus());
        self::assertEqualsCanonicalizing(['name', 'slug', 'ownerName', 'ownerEmail'], array_column($error['violations'], 'field'));
    }

    public function testTheListShowsEachConsultantWithItsOwnerAndPeople(): void
    {
        $account = $this->createAccount('Finanzas Claras');
        $this->createOwner($account);
        $this->createAssistant($account);
        $this->createClientLogin($account);
        $this->createClientLogin($account, 'otro@demo.test');
        $suspended = $this->createAccount('Plata Sana');
        $this->save($suspended->setActive(false));
        $this->actAs($this->superAdmin);

        $page = $this->api('GET', '/api/platform/accounts');
        self::assertSame(['Plata Sana', 'Finanzas Claras'], array_column($page['items'], 'name'), 'newest first');
        $row = $page['items'][1];
        self::assertSame([1, 2, 'asesor@demo.test'], [$row['assistants'], $row['clients'], $row['owner']['email']]);
        self::assertNull($page['items'][0]['owner']);

        self::assertSame(['Finanzas Claras'], array_column($this->api('GET', '/api/platform/accounts?status=active')['items'], 'name'));
        self::assertSame(['Plata Sana'], array_column($this->api('GET', '/api/platform/accounts?status=suspended')['items'], 'name'));
        $this->api('GET', '/api/platform/accounts?status=paused');
        self::assertSame(400, $this->responseStatus());
    }

    public function testDataLimitsAndFeaturesChangeOnlyWhatIsSent(): void
    {
        $account = $this->createAccount('Finanzas Claras');
        $this->actAs($this->superAdmin);
        $path = '/api/platform/accounts/'.$account->getId();

        $changed = $this->api('PATCH', $path, ['name' => 'Finanzas Claras SAS', 'locale' => 'es_MX', 'timezone' => 'America/Mexico_City', 'currency' => 'MXN']);
        self::assertSame(['Finanzas Claras SAS', 'finanzas-claras', 'es_MX', 'America/Mexico_City', 'MXN', 3], [$changed['name'], $changed['slug'], $changed['locale'], $changed['timezone'], $changed['currency'], $changed['maxAssistants']]);

        $changed = $this->api('PATCH', $path, ['maxAssistants' => 0, 'maxFileMb' => 25, 'features' => ['portal', 'booking']]);
        self::assertSame([0, 25, 10, ['booking', 'portal']], [$changed['maxAssistants'], $changed['maxFileMb'], $changed['maxPublishedPages'], $changed['features']]);
        self::assertSame('Finanzas Claras SAS', $changed['name']);

        $changed = $this->api('PATCH', $path, ['slug' => 'finanzas-claras']);
        self::assertSame(200, $this->responseStatus(), 'a consultant keeps its own address');
    }

    public function testChangesAreValidated(): void
    {
        $account = $this->createAccount('Finanzas Claras');
        $this->createAccount('Plata Sana');
        $this->actAs($this->superAdmin);
        $path = '/api/platform/accounts/'.$account->getId();

        $error = $this->api('PATCH', $path, ['country' => 'XX', 'timezone' => 'Marte/Olympus', 'locale' => 'fr_FR', 'maxAssistants' => -1, 'maxFileMb' => 0, 'features' => ['teleport']]);
        self::assertSame(422, $this->responseStatus());
        self::assertEqualsCanonicalizing(['country', 'timezone', 'locale', 'maxAssistants', 'maxFileMb', 'features'], array_column($error['violations'], 'field'));

        $this->api('PATCH', $path, ['slug' => 'plata-sana']);
        self::assertSame(422, $this->responseStatus());
        $this->api('PATCH', $path, ['maxAssistants' => '3']);
        self::assertSame(422, $this->responseStatus(), 'a number sent as text is refused');
    }

    public function testASuspendedConsultantIsOutUntilReactivated(): void
    {
        $account = $this->createAccount('Finanzas Claras');
        $owner = $this->createOwner($account);
        $this->actAs($this->superAdmin);

        self::assertFalse($this->api('POST', '/api/platform/accounts/'.$account->getId().'/suspend')['active']);
        $this->actAs($owner);
        $this->api('GET', '/api/me');
        self::assertSame(401, $this->responseStatus());
        $this->client->request('GET', '/finanzas-claras');
        self::assertSame(404, $this->responseStatus(), 'and its public address answers nothing');

        $this->actAs($this->superAdmin);
        self::assertTrue($this->api('POST', '/api/platform/accounts/'.$account->getId().'/reactivate')['active']);
        $this->actAs($owner);
        $this->api('GET', '/api/me');
        self::assertSame(200, $this->responseStatus());
    }

    public function testTheSuperAdminLooksAfterTheOwnerAndAssistants(): void
    {
        $account = $this->createAccount('Finanzas Claras');
        $owner = User::createOwner($account, 'nuevo@demo.test', 'Nuevo Asesor');
        $owner->issueInvitation();
        $this->save($owner);
        $assistant = $this->createAssistant($account);
        $this->createClientLogin($account);
        $this->actAs($this->superAdmin);
        $base = '/api/platform/accounts/'.$account->getId().'/users';

        self::assertSame(['Nuevo Asesor', 'Sofía Asistente'], array_column($this->api('GET', $base)['items'], 'fullName'), 'owner first, no clients');

        $this->api('POST', $base.'/'.$owner->getId().'/resend-invitation');
        self::assertSame(200, $this->responseStatus());
        self::assertEmailCount(1);

        self::assertFalse($this->api('DELETE', $base.'/'.$assistant->getId())['active']);
        self::assertTrue($this->api('POST', $base.'/'.$assistant->getId().'/enable')['active']);
        $error = $this->api('DELETE', $base.'/'.$owner->getId());
        self::assertSame(409, $this->responseStatus());
        self::assertSame('owner_cannot_be_disabled', $error['error']);
    }

    public function testUnknownIdsAndOtherConsultantsPeopleAreNotFound(): void
    {
        $account = $this->createAccount('Finanzas Claras');
        $theirs = $this->createAssistant($this->createAccount('Plata Sana'), 'suyo@demo.test');
        $client = $this->createClientLogin($account);
        $this->actAs($this->superAdmin);

        $this->api('GET', '/api/platform/accounts/0192f1e0-0000-7000-8000-000000000000');
        self::assertSame(404, $this->responseStatus());
        foreach ([$theirs, $client] as $user) {
            $this->api('DELETE', '/api/platform/accounts/'.$account->getId().'/users/'.$user->getId());
            self::assertSame(404, $this->responseStatus(), 'another consultant\'s assistant, or a client, is not this team');
        }
    }

    public function testOnlyASuperAdminManagesConsultants(): void
    {
        $account = $this->createAccount('Finanzas Claras');
        $people = [$this->createOwner($account), $this->createAssistant($account), $this->createClientLogin($account)];
        $id = (string) $account->getId();

        foreach ($people as $user) {
            $this->actAs($user);
            foreach ([['GET', '/api/platform/accounts'], ['POST', '/api/platform/accounts'], ['PATCH', '/api/platform/accounts/'.$id], ['POST', '/api/platform/accounts/'.$id.'/suspend']] as [$method, $path]) {
                $this->api($method, $path, 'GET' === $method ? null : []);
                self::assertSame(403, $this->responseStatus(), $method.' '.$path);
            }
        }
        $this->em()->clear();
        self::assertTrue($this->em()->find(Account::class, $account->getId())?->isActive());
    }
}
