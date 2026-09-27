<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A stage of the board, with its people. */
final readonly class BoardColumnOutput
{
    public function __construct(
        public string $stageId,
        public string $name,
        public string $kind,
        public ?int $alertDays,
        /** @var list<BoardCardOutput> the longest waiting first */
        public array $cards,
    ) {
    }
}
