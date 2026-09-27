<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One consultant, everything the super admin sets for it. */
final readonly class AccountDetailOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $country,
        public string $currency,
        public string $locale,
        public string $timezone,
        public bool $active,
        /** ISO 8601. */
        public string $createdAt,
        public int $maxPublishedPages,
        public int $maxAssistants,
        public int $storageMb,
        public int $maxFileMb,
        /** @var list<string> the features that are on: "booking", "payments", "portal", "flows" */
        public array $features,
        public ?AccountOwnerOutput $owner,
        public int $assistants,
        public int $clients,
    ) {
    }
}
