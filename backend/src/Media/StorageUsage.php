<?php

declare(strict_types=1);

namespace App\Media;

use App\Repository\ClientFileRepository;
use App\Repository\MediaAssetRepository;

/** What counts towards a consultant's storage limit: page images (with their copies) and contacts' files. */
final class StorageUsage
{
    public function __construct(
        private readonly MediaAssetRepository $media,
        private readonly ClientFileRepository $files,
    ) {
    }

    public function usedBytes(): int
    {
        return $this->media->totalBytes() + $this->files->totalBytes();
    }
}
