<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine;

use App\Doctrine\AccountScopeFilter;
use App\Entity\Account;
use App\Entity\AccountOwnedInterface;
use App\Entity\AccountOwnedTrait;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;

/**
 * The filter that keeps every consultant's rows to themselves fails closed: without an account it matches nothing,
 * with one it matches only that account's rows, and it leaves tables that belong to nobody (users, accounts) alone.
 */
final class AccountScopeFilterTest extends TestCase
{
    public function testWithoutAnAccountAnOwnedTableMatchesNothing(): void
    {
        self::assertSame('1 = 0', $this->filter()->addFilterConstraint($this->ownedMetadata(), 't0'));
    }

    public function testWithAnAccountAnOwnedTableMatchesOnlyItsRows(): void
    {
        $filter = $this->filter();
        $filter->setParameter(AccountScopeFilter::PARAMETER, 'abcdef0123456789abcdef0123456789');

        self::assertSame("t0.account_id = UNHEX('abcdef0123456789abcdef0123456789')", $filter->addFilterConstraint($this->ownedMetadata(), 't0'));
    }

    public function testTablesOwnedByNoAccountAreLeftAlone(): void
    {
        foreach ([User::class, Account::class] as $class) {
            self::assertSame('', $this->filter()->addFilterConstraint(new ClassMetadata($class), 't0'), $class);
        }
    }

    private function filter(): AccountScopeFilter
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('quote')->willReturnCallback(static fn (string $value): string => "'".$value."'");
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        return new AccountScopeFilter($em);
    }

    /**
     * @return ClassMetadata<OwnedThing>
     */
    private function ownedMetadata(): ClassMetadata
    {
        $metadata = new ClassMetadata(OwnedThing::class);
        $metadata->mapManyToOne([
            'fieldName' => 'account',
            'targetEntity' => Account::class,
            'joinColumns' => [['name' => 'account_id', 'referencedColumnName' => 'id']],
        ]);

        return $metadata;
    }
}

/** A stand-in for any customer-owned entity. */
final class OwnedThing implements AccountOwnedInterface
{
    use AccountOwnedTrait;
}
