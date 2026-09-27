<?php

declare(strict_types=1);

namespace App\Media;

use App\Entity\MediaAsset;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Where images live on disk: UPLOADS_DIR/<account id>/media/<asset id>/original.<ext> and <width>.webp. Outside the web
 * root: the public controller streams them (a cPanel account serves nothing else from there).
 */
final class MediaStorage
{
    public function __construct(
        #[Autowire('%env(resolve:UPLOADS_DIR)%')]
        private readonly string $root,
    ) {
    }

    public function directory(Uuid $accountId, Uuid $assetId): string
    {
        return sprintf('%s/%s/media/%s', rtrim($this->root, '/'), $accountId->toRfc4122(), $assetId->toRfc4122());
    }

    public function variantPath(MediaAsset $asset, int $width): string
    {
        $account = $asset->getAccount() ?? throw new \LogicException('An asset belongs to an account.');

        return $this->directory($account->getId(), $asset->getId()).'/'.$width.'.webp';
    }
}
