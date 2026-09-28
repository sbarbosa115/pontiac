<?php

declare(strict_types=1);

namespace App\Api\Output;

/** A file of a contact's (Archivos). */
final readonly class ClientFileOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $contentType,
        public int $sizeBytes,
        /** The client sees it (always true for what they uploaded). */
        public bool $shared,
        public bool $active,
        /** Uploaded by the client, from the portal. */
        public bool $byClient,
        public string $uploadedBy,
        /** ISO 8601. */
        public string $createdAt,
    ) {
    }
}
