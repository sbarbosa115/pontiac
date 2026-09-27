<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Account;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * No list costs one query per row. Each list is read with one row and again with five, and the number of queries
 * must not move: a list that grows with its rows has an N+1 (a relation the Presenter reads that the repository did
 * not fetch-join), which demo data is usually too small to show. Add every new list here.
 */
final class ListQueryCountTest extends ApiTestCase
{
    private Account $account;
    private User $owner;
    private int $row = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->createAccount();
        $this->owner = $this->createOwner($this->account);
    }

    /**
     * @return iterable<string, array{string, string}> [path, the row it adds]
     */
    public static function lists(): iterable
    {
        yield 'equipo' => ['/api/admin/team', 'assistant'];
    }

    #[DataProvider('lists')]
    public function testAListCostsTheSameQueriesWithOneRowAsWithFive(string $path, string $row): void
    {
        $this->{$row}();
        $one = $this->queriesFor($this->owner, $path);
        for ($i = 0; $i < 4; ++$i) {
            $this->{$row}();
        }
        $five = $this->queriesFor($this->owner, $path);

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
    }

    // One row of each list, complete enough for its Presenter to read every relation it shows.

    private function assistant(): void
    {
        ++$this->row;
        $this->createAssistant($this->account, sprintf('asistente%d@demo.test', $this->row), 'Asistente '.$this->row);
    }
}
