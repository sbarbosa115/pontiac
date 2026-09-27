<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Base for repositories of AccountOwnedInterface entities.
 *
 * Use findOneById() instead of find(): find() returns an entity straight from
 * the identity map without running SQL, so it can hand back a row the
 * AccountScopeFilter would have hidden (long-running workers, tests).
 *
 * @template T of object
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class AccountOwnedRepository extends ServiceEntityRepository
{
    use ListQueries;

    /**
     * @return T|null
     */
    public function findOneById(string $id): ?object
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->createQueryBuilder('e')
            ->andWhere('e.id = :id')
            ->setParameter('id', Uuid::fromString($id), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The rows with these ids that the current user can see, through the same filters as everything else: an
     * unknown id or another account's is simply missing — which is
     * how a caller tells that a list of ids is not all theirs.
     *
     * @param list<mixed> $ids
     *
     * @return list<T>
     */
    public function findByIds(array $ids): array
    {
        $valid = array_values(array_unique(array_filter($ids, static fn ($id) => \is_string($id) && Uuid::isValid($id))));
        if ([] === $valid) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->andWhere('e.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (string $id) => Uuid::fromString($id)->toBinary(), $valid), ArrayParameterType::BINARY)
            ->getQuery()
            ->getResult();
    }

    /**
     * Adds "$field = :$parameter" for an optional id filter; an invalid UUID matches nothing.
     */
    protected static function whereId(QueryBuilder $qb, string $field, string $parameter, ?string $id): void
    {
        if (null === $id || '' === $id) {
            return;
        }

        if (!Uuid::isValid($id)) {
            $qb->andWhere('1 = 0');

            return;
        }

        $qb->andWhere(sprintf('%s = :%s', $field, $parameter))->setParameter($parameter, Uuid::fromString($id), UuidType::NAME);
    }

    /**
     * Ids for an "IN (:ids)" parameter. Bind them with ArrayParameterType::BINARY: Doctrine's UuidType has no
     * native MySQL counterpart, so every id column is BINARY(16) and a text UUID would match nothing.
     *
     * @param list<object> $entities entities with a Uuid getId()
     *
     * @return list<string> the sixteen bytes of each id
     */
    protected static function ids(array $entities): array
    {
        return array_map(static fn (object $entity) => $entity->getId()->toBinary(), $entities);
    }

    /**
     * The canonical text of an id selected with IDENTITY(): Doctrine returns the raw column value, which is
     * sixteen bytes on MySQL, while callers key their arrays by (string) $entity->getId().
     *
     * @param array<array-key, array<string, mixed>> $rows
     *
     * @return array<array-key, array<string, mixed>> the same rows, with $column holding the text id
     */
    protected static function withTextIds(array $rows, string $column): array
    {
        return array_map(static function (array $row) use ($column): array {
            $row[$column] = Uuid::fromString((string) $row[$column])->toRfc4122();

            return $row;
        }, $rows);
    }
}
