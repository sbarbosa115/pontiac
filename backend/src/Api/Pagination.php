<?php

declare(strict_types=1);

namespace App\Api;

/**
 * Which page of a list to read. The HTTP layer builds it from ?page= and ?perPage= (ApiController::pagination()).
 */
final class Pagination
{
    public const MAX_PER_PAGE = 100;

    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 25,
    ) {
    }

    /**
     * Out-of-range values are brought back in range, never refused: page 0 is page 1, perPage 500 is the maximum.
     */
    public static function of(int $page, int $perPage): self
    {
        return new self(max(1, $page), min(self::MAX_PER_PAGE, max(1, $perPage)));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
