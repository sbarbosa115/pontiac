<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One image of the consultant's library (Ajustes › Medios). */
final readonly class MediaAssetOutput
{
    public function __construct(
        public string $id,
        public string $originalName,
        public int $width,
        public int $height,
        /** The original and its copies, what counts towards storage. */
        public int $sizeBytes,
        public string $altText,
        public bool $active,
        /** ISO 8601. */
        public string $createdAt,
        /** A small copy, for thumbnails. */
        public string $thumbUrl,
        /** The widest copy. */
        public string $url,
    ) {
    }
}
