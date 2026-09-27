<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\MediaAsset;
use App\Entity\OutgoingEmail;
use App\Entity\PlatformSettingsChange;
use App\Entity\User;
use App\Enum\EmailStatus;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

/**
 * No list costs one query per row. Each list is read with one row and again with five, and the number of queries
 * must not move: a list that grows with its rows has an N+1 (a relation the Presenter reads that the repository did
 * not fetch-join), which demo data is usually too small to show. Add every new list here.
 */
final class ListQueryCountTest extends ApiTestCase
{
    private Account $account;
    private User $owner;
    private User $superAdmin;
    private int $row = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->createAccount();
        $this->owner = $this->createOwner($this->account);
        $this->superAdmin = $this->createSuperAdmin();
    }

    /**
     * @return iterable<string, array{string, string, string}> [who reads it, path, the row it adds]
     */
    public static function lists(): iterable
    {
        yield 'equipo' => ['owner', '/api/admin/team', 'assistant'];
        yield 'asesores' => ['superAdmin', '/api/platform/accounts', 'consultant'];
        yield 'equipo de un asesor' => ['superAdmin', '/api/platform/accounts/{account}/users', 'assistant'];
        yield 'administradores' => ['superAdmin', '/api/platform/admins', 'superAdmin'];
        yield 'historial de configuración' => ['superAdmin', '/api/platform/settings/history', 'settingsChange'];
        yield 'correos' => ['superAdmin', '/api/platform/emails', 'email'];
        yield 'páginas' => ['owner', '/api/admin/pages', 'page'];
        yield 'categorías' => ['owner', '/api/admin/categories', 'category'];
        yield 'medios' => ['owner', '/api/admin/media', 'image'];
        yield 'prospectos' => ['owner', '/api/admin/contacts', 'contact'];
    }

    #[DataProvider('lists')]
    public function testAListCostsTheSameQueriesWithOneRowAsWithFive(string $who, string $path, string $row): void
    {
        $path = str_replace('{account}', (string) $this->account->getId(), $path);
        $this->{$row}();
        $one = $this->queriesFor($this->{$who}, $path);
        for ($i = 0; $i < 4; ++$i) {
            $this->{$row}();
        }
        $five = $this->queriesFor($this->{$who}, $path);

        self::assertSame(
            $one['queries'],
            $five['queries'],
            sprintf('%s ran %d queries for %d row(s) and %d for %d: a relation is loaded row by row (N+1).', $path, $one['queries'], $one['rows'], $five['queries'], $five['rows']),
        );
        self::assertGreaterThan($one['rows'], $five['rows'], 'the fixture must actually add rows to this list');
    }

    /**
     * @return array{queries: int, rows: int}
     */
    private function queriesFor(User $user, string $path): array
    {
        $this->em()->clear();
        $this->actAs($user);
        $url = $path.(str_contains($path, '?') ? '&' : '?').'perPage=50';
        // The first request of a test pays for one-off work (the security token, metadata): warm up, then measure.
        $this->api('GET', $url);
        $this->em()->clear();
        $this->client->enableProfiler();
        $body = $this->api('GET', $url);
        self::assertSame(200, $this->responseStatus(), $path);
        $profile = $this->client->getProfile();
        self::assertNotFalse($profile, 'the profiler must be on for this request');

        $db = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $db);
        $measured = ['queries' => $db->getQueryCount(), 'rows' => \count($body['items'] ?? [])];
        $this->reattach();

        return $measured;
    }

    /** The clear() above detached the fixture: take it back as managed references before adding more rows. */
    private function reattach(): void
    {
        $this->account = $this->em()->getReference(Account::class, $this->account->getId());
        $this->owner = $this->em()->getReference(User::class, $this->owner->getId());
        $this->superAdmin = $this->em()->getReference(User::class, $this->superAdmin->getId());
    }

    // One row of each list, complete enough for its Presenter to read every relation it shows.

    private function assistant(): void
    {
        ++$this->row;
        $this->createAssistant($this->account, sprintf('asistente%d@demo.test', $this->row), 'Asistente '.$this->row);
    }

    private function consultant(): void
    {
        ++$this->row;
        $account = $this->createAccount('Asesor '.$this->row, 'asesor-'.$this->row);
        $this->createOwner($account, sprintf('dueno%d@demo.test', $this->row), 'Dueño '.$this->row);
        $this->createAssistant($account, sprintf('ayuda%d@demo.test', $this->row), 'Ayuda '.$this->row);
        $this->createClientLogin($account, sprintf('cliente%d@demo.test', $this->row));
    }

    private function superAdmin(): void
    {
        ++$this->row;
        $this->createSuperAdmin(sprintf('admin%d@pontiac.test', $this->row));
    }

    private function settingsChange(): void
    {
        $this->save(new PlatformSettingsChange($this->superAdmin, ['minNoticeHours' => ['from' => $this->row, 'to' => ++$this->row]]));
    }

    private function email(): void
    {
        // As the email log writes it, for a consultant so the list shows its name.
        $email = new OutgoingEmail($this->account, 'test', sprintf('persona%d@demo.test', ++$this->row), 'Hola', EmailStatus::Sent, null);
        $this->em()->getConnection()->insert('outgoing_email', $email->toRow());
    }

    private function page(): void
    {
        $this->createPage($this->account, 'pagina-'.++$this->row);
    }

    private function category(): void
    {
        $this->createCategory($this->account, 'Categoría '.++$this->row);
    }

    private function image(): void
    {
        $this->save(new MediaAsset($this->account, Uuid::v7(), 'foto'.++$this->row.'.jpg', 'image/jpeg', 1000, 800, 600, [480, 800]));
    }

    private function contact(): void
    {
        // With a category and a source page: the two relations the list shows.
        ++$this->row;
        $page = $this->createPage($this->account, 'origen-'.$this->row);
        $contact = (new Contact($this->account, 'Persona '.$this->row, sprintf('persona%d@demo.test', $this->row), null, $page, 'hash'))
            ->setCategory($this->createCategory($this->account, 'Cat '.$this->row));
        $this->save($contact);
    }
}
