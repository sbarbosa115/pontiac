<?php

declare(strict_types=1);

namespace App\Page;

use App\Booking\SessionTime;
use App\Booking\SlotFinder;
use App\Entity\Account;
use App\Entity\LandingPage;
use App\Entity\MediaAsset;
use App\Entity\Plan;
use App\Enum\AccountFeature;
use App\Repository\AvailabilityRepository;
use App\Repository\MediaAssetRepository;
use App\Repository\PlanRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A landing page as HTML: what visitors get (the published content) and what the editor previews (the draft, never
 * indexed, its form inert). Server-rendered on purpose — search engines read it as it is — with its CSS inline and no
 * framework JavaScript.
 */
final class PageRenderer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly MediaAssetRepository $media,
        private readonly TimeToken $timeToken,
        private readonly PlanRepository $plans,
        private readonly AvailabilityRepository $availability,
        private readonly SlotFinder $slots,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
    ) {
    }

    /**
     * @param array<string, mixed>  $content the published content, or a draft for the preview
     * @param array<string, string> $values  what the visitor typed, when the form comes back with errors
     * @param array<string, string> $errors  by field name
     * @param array{values?: array<string, string>, errors?: array<string, string>, booked?: bool} $booking the booking form's state
     */
    public function render(Account $account, LandingPage $page, array $content, bool $preview = false, array $values = [], array $errors = [], bool $sent = false, int $status = 200, array $booking = []): Response
    {
        $images = $this->images($content);
        $bookings = $this->bookings($account, $content);
        $path = '/'.$account->getSlug().($page->isHome() ? '' : '/'.$page->getSlug());
        $url = rtrim($this->appUrl, '/').$path;
        $accent = TemplateCatalog::ACCENTS[$content['settings']['accent'] ?? 'navy'] ?? TemplateCatalog::ACCENTS['navy'];
        $seoImage = $images[$content['seo']['imageId'] ?? ''] ?? $this->firstImage($content, $images);

        $html = $this->twig->render('public/page/page.html.twig', [
            'account' => $account,
            'page' => $page,
            'content' => $content,
            // A booking section shows only while it has something to book: the feature on, its free plan active.
            'sections' => array_values(array_filter($content['sections'], static fn (array $s) => $s['enabled'] && ('booking' !== $s['type'] || isset($bookings[$s['id']])))),
            'bookings' => $bookings,
            'bookingValues' => $booking['values'] ?? [],
            'bookingErrors' => $booking['errors'] ?? [],
            'booked' => $booking['booked'] ?? false,
            'images' => $images,
            'accent' => ['color' => $accent[0], 'on' => $accent[1], 'soft' => $accent[2]],
            'preview' => $preview,
            'url' => $url,
            'action' => $path.'/enviar',
            'bookingAction' => $path.'/reservar',
            'eventDates' => $this->eventDates($account, $content),
            'privacyUrl' => '/'.$account->getSlug().'/privacidad',
            'mediaBase' => '/'.$account->getSlug().'/media/',
            'socialImage' => null === $seoImage ? null : rtrim($this->appUrl, '/').'/'.$account->getSlug().'/media/'.$seoImage->getId().'-'.$seoImage->variantFor(1200).'.webp',
            'jsonLd' => $this->jsonLd($account, $content, $url),
            'timeToken' => $this->timeToken->issue(),
            'values' => $values,
            'errors' => $errors,
            'sent' => $sent,
        ]);

        $response = new Response($html, $status);
        if ($preview) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, MediaAsset> every image the content shows, by id
     */
    private function images(array $content): array
    {
        $ids = [];
        foreach ($content['sections'] as $section) {
            foreach (TemplateCatalog::SECTION_TYPES[$section['type']] as $name => $spec) {
                if ('image' === $spec['kind'] && \is_string($section['fields'][$name] ?? null)) {
                    $ids[] = $section['fields'][$name];
                }
            }
        }
        if (\is_string($content['seo']['imageId'] ?? null)) {
            $ids[] = $content['seo']['imageId'];
        }

        $images = [];
        foreach ($this->media->findByIds(array_values(array_unique($ids))) as $asset) {
            $images[(string) $asset->getId()] = $asset;
        }

        return $images;
    }

    /**
     * @param array<string, mixed>      $content
     * @param array<string, MediaAsset> $images
     */
    private function firstImage(array $content, array $images): ?MediaAsset
    {
        foreach ($content['sections'] as $section) {
            if ($section['enabled'] && isset($images[$section['fields']['image'] ?? ''])) {
                return $images[$section['fields']['image']];
            }
        }

        return null;
    }

    /**
     * For each enabled booking section: its plan and the free slots, grouped by the day they fall on (local time).
     *
     * @param array<string, mixed> $content
     *
     * @return array<string, array{plan: Plan, days: list<array{label: string, slots: list<array{value: string, label: string}>}>}>
     */
    private function bookings(Account $account, array $content): array
    {
        if (!$account->hasFeature(AccountFeature::Booking)) {
            return [];
        }
        $bookings = [];
        foreach ($content['sections'] as $section) {
            if ('booking' !== $section['type'] || !$section['enabled'] || null === ($section['fields']['planId'] ?? null)) {
                continue;
            }
            $plan = $this->plans->findOneById($section['fields']['planId']);
            if (null === $plan || !$plan->isActive() || !$plan->isFree()) {
                continue;
            }
            $days = [];
            foreach ($this->slots->slots($account, $this->availability->forAccount($account), $plan->getDurationMinutes(), new \DateTimeImmutable()) as $slot) {
                $label = SessionTime::date($account, $slot);
                $days[$label][] = ['value' => $slot->format(\DATE_ATOM), 'label' => SessionTime::time($account, $slot)];
            }
            $bookings[$section['id']] = [
                'plan' => $plan,
                'days' => array_map(static fn (string $label, array $slots) => ['label' => $label, 'slots' => $slots], array_keys($days), array_values($days)),
            ];
        }

        return $bookings;
    }

    /**
     * The event dates as the account's locale writes them ("sábado, 14 de noviembre de 2026"), by section id.
     *
     * @param array<string, mixed> $content
     *
     * @return array<string, string>
     */
    private function eventDates(Account $account, array $content): array
    {
        $formatter = new \IntlDateFormatter($account->getLocale(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, 'UTC');
        $dates = [];
        foreach ($content['sections'] as $section) {
            if ('event' === $section['type'] && null !== ($section['fields']['date'] ?? null)) {
                $dates[$section['id']] = (string) $formatter->format(new \DateTimeImmutable($section['fields']['date'].' 00:00:00', new \DateTimeZone('UTC')));
            }
        }

        return $dates;
    }

    /**
     * Structured data for search engines: who the consultant is, their FAQ, their event.
     *
     * @param array<string, mixed> $content
     */
    private function jsonLd(Account $account, array $content, string $url): string
    {
        $graph = [[
            '@type' => 'ProfessionalService',
            'name' => $account->getName(),
            'url' => rtrim($this->appUrl, '/').'/'.$account->getSlug(),
            'description' => '' !== $content['seo']['description'] ? $content['seo']['description'] : null,
            'areaServed' => $account->getCountry(),
        ]];
        foreach ($content['sections'] as $section) {
            if (!$section['enabled']) {
                continue;
            }
            if ('faq' === $section['type'] && [] !== $section['fields']['items']) {
                $graph[] = [
                    '@type' => 'FAQPage',
                    'mainEntity' => array_map(static fn (array $item) => [
                        '@type' => 'Question',
                        'name' => $item['question'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
                    ], $section['fields']['items']),
                ];
            }
            if ('event' === $section['type'] && null !== $section['fields']['date']) {
                $graph[] = [
                    '@type' => 'Event',
                    'name' => $content['seo']['title'],
                    'startDate' => $section['fields']['date'],
                    'location' => ['@type' => 'Place', 'name' => $section['fields']['place']],
                    'organizer' => ['@type' => 'Organization', 'name' => $account->getName(), 'url' => $url],
                ];
            }
        }

        $data = ['@context' => 'https://schema.org', '@graph' => array_map(static fn (array $node) => array_filter($node, static fn ($v) => null !== $v), $graph)];

        // Safe inside <script>: "</script>" in a text cannot close the tag.
        return (string) json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_HEX_AMP);
    }
}
