<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * What every list endpoint's query needs: the search box (?q=) and one page at a time.
 */
trait ListQueries
{
    /**
     * Adds the table's free-text filter: every list in the UI has a search box, and each repository decides
     * which fields it looks at. An empty term matches everything.
     *
     * @param list<string> $fields DQL paths, e.g. ['t.fullName', 'u.name']
     */
    protected static function whereTerm(QueryBuilder $qb, string $term, array $fields): void
    {
        $term = trim($term);
        if ('' === $term || [] === $fields) {
            return;
        }

        $conditions = array_map(static fn (string $field) => sprintf('LOWER(%s) LIKE :term', $field), $fields);
        $qb->andWhere('('.implode(' OR ', $conditions).')')->setParameter('term', self::likePattern($term));
    }

    protected static function likePattern(string $term): string
    {
        return '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
    }

    /**
     * One page of the query and how many rows it has in all.
     *
     * @param bool $fetchJoinCollection true when the query fetch-joins a to-many association
     */
    protected static function paginate(QueryBuilder $qb, Pagination $pagination, bool $fetchJoinCollection = false): Page
    {
        $qb->setFirstResult($pagination->offset())->setMaxResults($pagination->perPage);
        $paginator = new Paginator($qb, $fetchJoinCollection);

        return new Page(iterator_to_array($paginator, false), \count($paginator), $pagination);
    }
}
