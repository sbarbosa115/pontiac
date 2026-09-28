<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use App\Entity\Account;
use App\Entity\LandingPage;
use App\Enum\PageStatus;
use App\Enum\PageTemplate;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AccountOwnedRepository<LandingPage>
 */
class LandingPageRepository extends AccountOwnedRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LandingPage::class);
    }

    /** The home page first, then the most recently changed; ?q= searches title and address. */
    public function search(string $term, ?PageStatus $status, ?PageTemplate $template, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.home', 'DESC')
            ->addOrderBy('p.updatedAt', 'DESC')
            ->addOrderBy('p.id', 'DESC');
        if (null !== $status) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }
        if (null !== $template) {
            $qb->andWhere('p.template = :template')->setParameter('template', $template);
        }
        self::whereTerm($qb, $term, ['p.title', 'p.slug']);

        return self::paginate($qb, $pagination);
    }

    public function findOneBySlug(string $slug): ?LandingPage
    {
        return $this->findOneBy(['slug' => Account::normalizeSlug($slug)]);
    }

    public function findHome(): ?LandingPage
    {
        return $this->findOneBy(['home' => true]);
    }

    public function countPublished(): int
    {
        return $this->count(['status' => PageStatus::Published]);
    }

    /**
     * The published pages search engines may list, for the sitemap (a page marked "no indexar" is left out).
     *
     * @return list<LandingPage>
     */
    public function findIndexable(): array
    {
        $pages = $this->findBy(['status' => PageStatus::Published], ['home' => 'DESC', 'slug' => 'ASC']);

        return array_values(array_filter($pages, static fn (LandingPage $page) => false !== ($page->getPublished()['seo']['index'] ?? true)));
    }

    /**
     * @return list<LandingPage>
     */
    public function findAllForPickers(): array
    {
        return $this->createQueryBuilder('p')->orderBy('p.title', 'ASC')->getQuery()->getResult();
    }
}
