<?php

declare(strict_types=1);

namespace App\Api;

/**
 * One page of a list and how many there are in all: what a repository returns for a paginated list, and what
 * ApiController::page() turns into {items, total, page, perPage}.
 */
final class Page
{
    /**
     * @param list<object> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly Pagination $pagination,
    ) {
    }
}
