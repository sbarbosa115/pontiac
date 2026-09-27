<?php

declare(strict_types=1);

namespace App\Media;

use App\Api\ApiException;
use App\Api\ApiValidationException;
use App\Entity\Account;
use App\Entity\MediaAsset;
use App\Repository\MediaAssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * An image into a consultant's library: its type read from its content (not its name), within the consultant's
 * file-size and storage limits, stored with its WebP copies.
 */
final class MediaUploader
{
    public const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_PIXELS_SIDE = 8000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MediaAssetRepository $media,
        private readonly MediaStorage $storage,
        private readonly ImageProcessor $images,
    ) {
    }

    public function upload(Account $account, ?UploadedFile $file): MediaAsset
    {
        if (null === $file || !$file->isValid()) {
            throw ApiValidationException::single('file', 'Choose an image to upload.');
        }
        $size = (int) $file->getSize();
        if ($size > $account->getMaxFileMb() * 1024 * 1024) {
            throw ApiValidationException::single('file', 'The image can be at most %max% MB.', ['%max%' => $account->getMaxFileMb()]);
        }
        $type = (string) (new \finfo(\FILEINFO_MIME_TYPE))->file($file->getPathname());
        $dimensions = @getimagesize($file->getPathname());
        if (!isset(self::TYPES[$type]) || false === $dimensions) {
            throw ApiValidationException::single('file', 'Upload a JPG, PNG or WebP image.');
        }
        [$width, $height] = $dimensions;
        if ($width > self::MAX_PIXELS_SIDE || $height > self::MAX_PIXELS_SIDE) {
            throw ApiValidationException::single('file', 'The image can be at most %max% pixels on each side.', ['%max%' => self::MAX_PIXELS_SIDE]);
        }

        $id = Uuid::v7();
        $directory = $this->storage->directory($account->getId(), $id);
        $widths = $this->images->writeVariants($file->getPathname(), $type, $directory, MediaAsset::WIDTHS);
        $file->move($directory, 'original.'.self::TYPES[$type]);

        $total = (int) array_sum(array_map(static fn (string $path) => (int) filesize($path), glob($directory.'/*') ?: []));
        if ($this->media->totalBytes() + $total > $account->getStorageMb() * 1024 * 1024) {
            self::remove($directory);
            throw ApiException::conflict('storage_limit_reached', sprintf('This consultant can store at most %d MB.', $account->getStorageMb()));
        }

        $asset = new MediaAsset($account, $id, (string) $file->getClientOriginalName(), $type, $total, $width, $height, $widths);
        $this->em->persist($asset);
        $this->em->flush();

        return $asset;
    }

    private static function remove(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($directory);
    }
}
