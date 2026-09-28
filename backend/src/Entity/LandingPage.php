<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PageStatus;
use App\Enum\PageTemplate;
use App\Repository\LandingPageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A consultant's public page (Páginas), built from one of the five templates. What the consultant edits is the draft;
 * visitors see what was last published, so a page can be reworked while the live one stays as it was.
 *
 * Content is JSON shaped by App\Page\PageContent: the template's sections (order, on/off, fields), the form's extra
 * fields, SEO and settings. App\Page\ContentValidator checks it against the template on every save.
 */
#[ORM\Entity(repositoryClass: LandingPageRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_page_account_slug', columns: ['account_id', 'slug'])]
#[ORM\Index(name: 'idx_page_account_status', columns: ['account_id', 'status'])]
class LandingPage implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    /** Page addresses the consultant's own routes use: /<consultant>/<these>. */
    public const RESERVED_SLUGS = ['portal', 'privacidad', 'sitemap.xml', 'media', 'reservar', 'pago', 'enviar', 'gracias'];

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(length: 60)]
    private string $slug;

    #[ORM\Column(length: 30, enumType: PageTemplate::class)]
    private PageTemplate $template;

    // The page at /<consultant>. One per account at most (LandingPageRepository::makeHome()).
    #[ORM\Column]
    private bool $home = false;

    #[ORM\Column(length: 10, enumType: PageStatus::class)]
    private PageStatus $status = PageStatus::Draft;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $draft;

    /** @var array<string, mixed>|null what visitors see; null until first published */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $published = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $draft
     */
    public function __construct(Account $account, string $title, string $slug, PageTemplate $template, array $draft)
    {
        $this->id = Uuid::v7();
        $this->setAccount($account);
        $this->title = $title;
        $this->slug = Account::normalizeSlug($slug);
        $this->template = $template;
        $this->draft = $draft;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public static function isReservedSlug(string $slug): bool
    {
        return \in_array(Account::normalizeSlug($slug), self::RESERVED_SLUGS, true);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function rename(string $title, string $slug): static
    {
        $this->title = $title;
        $this->slug = Account::normalizeSlug($slug);
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getTemplate(): PageTemplate
    {
        return $this->template;
    }

    public function isHome(): bool
    {
        return $this->home;
    }

    public function setHome(bool $home): static
    {
        $this->home = $home;

        return $this;
    }

    public function getStatus(): PageStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDraft(): array
    {
        return $this->draft;
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function saveDraft(array $draft): static
    {
        $this->draft = $draft;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPublished(): ?array
    {
        return $this->published;
    }

    /** The draft goes live. */
    public function publish(): static
    {
        $this->published = $this->draft;
        $this->status = PageStatus::Published;
        $this->publishedAt = new \DateTimeImmutable();

        return $this;
    }

    /** Taken down (410 for visitors); its content is kept. */
    public function disable(): static
    {
        $this->status = PageStatus::Disabled;

        return $this;
    }

    /** Back as it was: live again if it had been published, a draft otherwise. */
    public function reactivate(): static
    {
        $this->status = null === $this->published ? PageStatus::Draft : PageStatus::Published;

        return $this;
    }

    /**
     * Compared as content, not as stored: MySQL keeps a JSON object's keys sorted, so the same content can come back
     * from the draft and the published columns in different orders.
     */
    public function hasUnpublishedChanges(): bool
    {
        return null === $this->published || self::canonical($this->draft) !== self::canonical($this->published);
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private static function canonical(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }

        return array_map(static fn (mixed $v) => \is_array($v) ? self::canonical($v) : $v, $value);
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
