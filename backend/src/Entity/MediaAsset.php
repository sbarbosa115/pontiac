<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MediaAssetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * An image in a consultant's media library (Ajustes › Medios), used by their pages. The original is kept as uploaded;
 * the pages show WebP copies at a few widths (App\Media\ImageProcessor). Page images are public by nature: they are
 * served at /<consultant>/media/<id>-<width>.webp to anyone.
 */
#[ORM\Entity(repositoryClass: MediaAssetRepository::class)]
#[ORM\Index(name: 'idx_media_account_created', columns: ['account_id', 'created_at'])]
class MediaAsset implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    /** The widths pages ask for; a narrower original gets only the ones it can fill, plus its own. */
    public const WIDTHS = [480, 960, 1600];

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 40)]
    private string $contentType;

    // The original plus every variant: what the storage limit counts.
    #[ORM\Column]
    private int $totalBytes;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $width;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $height;

    /** @var list<int> the WebP widths written next to the original */
    #[ORM\Column(type: Types::JSON)]
    private array $variants;

    // Read aloud for people who cannot see the image, and by search engines.
    #[ORM\Column(length: 255)]
    private string $altText = '';

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param list<int> $variants
     */
    public function __construct(Account $account, Uuid $id, string $originalName, string $contentType, int $totalBytes, int $width, int $height, array $variants)
    {
        $this->id = $id;
        $this->setAccount($account);
        $this->originalName = mb_substr($originalName, 0, 255);
        $this->contentType = $contentType;
        $this->totalBytes = $totalBytes;
        $this->width = $width;
        $this->height = $height;
        $this->variants = $variants;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getTotalBytes(): int
    {
        return $this->totalBytes;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @return list<int>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /** The narrowest variant at least this wide, or the widest there is. */
    public function variantFor(int $width): int
    {
        foreach ($this->variants as $variant) {
            if ($variant >= $width) {
                return $variant;
            }
        }

        return $this->variants[array_key_last($this->variants)] ?? $this->width;
    }

    public function getAltText(): string
    {
        return $this->altText;
    }

    public function setAltText(string $altText): static
    {
        $this->altText = mb_substr(trim($altText), 0, 255);

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
