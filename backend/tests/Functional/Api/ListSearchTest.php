<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
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

    protected function setUp(): void
    {
        parent::setUp();

        $account = $this->createAccount();
        $this->owner = $this->createOwner($account, 'andres@demo.test', 'Andrés Asesor');
        $this->createAssistant($account, 'sofia@asistentes.test', 'Sofía Ruiz');
    }

    /**
     * @return iterable<string, array{string, string, string}> [path, a term matching one row, a term matching another]
     */
    public static function lists(): iterable
    {
        yield 'team by name' => ['/api/admin/team', 'Andrés', 'Ruiz'];
        yield 'team by email' => ['/api/admin/team', 'andres@', 'asistentes.test'];
    }

    #[DataProvider('lists')]
    public function testEveryListNarrowsToTheSearchTerm(string $path, string $matching, string $other): void
    {
        $this->actAs($this->owner);

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
