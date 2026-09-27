<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Input\PageCreateInput;
use App\Api\Input\PageUpdateInput;
use App\Api\InputMapper;
use App\Api\Output\PageCatalogOutput;
use App\Api\Output\PageDetailOutput;
use App\Api\Output\PageSummaryOutput;
use App\Api\Presenter;
use App\Entity\LandingPage;
use App\Entity\User;
use App\Enum\PageStatus;
use App\Enum\PageTemplate;
use App\Page\ContentValidator;
use App\Page\PageEditor;
use App\Page\PageRenderer;
use App\Repository\LandingPageRepository;
use App\Repository\LeadSubmissionRepository;
use App\Repository\PlatformSettingsRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Páginas: the consultant's landing pages. Owner and assistants write and publish; only the owner takes a page down
 * or brings it back.
 */
#[Route('/api/admin/pages', name: 'api_admin_page_')]
final class PageController extends ApiController
{
    public function __construct(
        private readonly LandingPageRepository $pages,
        private readonly PageEditor $editor,
        private readonly InputMapper $input,
    ) {
    }

    /** ?q= searches title and address; ?status= and ?template= narrow it. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(PageSummaryOutput::class, page: true)]
    public function list(Request $request, LeadSubmissionRepository $submissions): JsonResponse
    {
        $page = $this->pages->search(
            $request->query->getString('q'),
            $this->enumQuery($request->query->getString('status'), PageStatus::class, 'status'),
            $this->enumQuery($request->query->getString('template'), PageTemplate::class, 'template'),
            $this->pagination($request),
        );
        /** @var list<LandingPage> $found */
        $found = $page->items;
        $leads = $submissions->countByPageSince($found, new \DateTimeImmutable('-30 days'));

        return $this->page($page, static fn (object $p) => Presenter::pageSummary($p, $leads[(string) $p->getId()] ?? 0));
    }

    /** The templates, their sections and fields: what the editor draws. */
    #[Route('/catalog', name: 'catalog', methods: ['GET'])]
    #[ApiResponse(PageCatalogOutput::class)]
    public function catalog(PlatformSettingsRepository $settings): JsonResponse
    {
        return $this->json(Presenter::pageCatalog($settings->current()->getEnabledTemplates()));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[ApiResponse(PageDetailOutput::class, status: 201)]
    public function create(Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), PageCreateInput::class);
        $page = $this->editor->create($this->account(), trim((string) $data->title), (string) $data->slug, PageTemplate::from((string) $data->template));

        return $this->json(Presenter::pageDetail($page), 201);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[ApiResponse(PageDetailOutput::class)]
    public function show(string $id): JsonResponse
    {
        return $this->json(Presenter::pageDetail($this->load($id)));
    }

    /** Saves title, address and draft; visitors see nothing of it until it is published. */
    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    #[ApiResponse(PageDetailOutput::class)]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), PageUpdateInput::class);
        $page = $this->editor->update($this->load($id), null === $data->title ? null : trim($data->title), $data->slug, $data->draft);

        return $this->json(Presenter::pageDetail($page));
    }

    /**
     * The editor's live preview: a draft (not saved) as visitors would see it. HTML, for an iframe's srcdoc; a draft
     * that is not valid yet answers the 422 the save would.
     */
    #[Route('/{id}/preview', name: 'preview', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function preview(string $id, Request $request, ContentValidator $validator, PageRenderer $renderer): Response
    {
        $page = $this->load($id);
        $draft = $this->input->json($request)['draft'] ?? $page->getDraft();
        $content = $validator->validate($page->getTemplate(), \is_array($draft) ? $draft : []);

        return $renderer->render($this->account(), $page, $content, preview: true);
    }

    #[Route('/{id}/publish', name: 'publish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(PageDetailOutput::class)]
    public function publish(string $id): JsonResponse
    {
        return $this->json(Presenter::pageDetail($this->editor->publish($this->load($id))));
    }

    /** Takes it down: visitors get a 410, search engines drop it. Its content is kept. */
    #[Route('/{id}/disable', name: 'disable', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(PageDetailOutput::class)]
    public function disable(string $id): JsonResponse
    {
        return $this->json(Presenter::pageDetail($this->editor->disable($this->load($id))));
    }

    #[Route('/{id}/reactivate', name: 'reactivate', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(PageDetailOutput::class)]
    public function reactivate(string $id): JsonResponse
    {
        return $this->json(Presenter::pageDetail($this->editor->reactivate($this->load($id))));
    }

    #[Route('/{id}/duplicate', name: 'duplicate', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(PageDetailOutput::class, status: 201)]
    public function duplicate(string $id): JsonResponse
    {
        return $this->json(Presenter::pageDetail($this->editor->duplicate($this->load($id))), 201);
    }

    /** Makes it the page at /<consultant>. */
    #[Route('/{id}/home', name: 'home', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(PageDetailOutput::class)]
    public function home(string $id): JsonResponse
    {
        return $this->json(Presenter::pageDetail($this->editor->makeHome($this->load($id))));
    }

    private function load(string $id): LandingPage
    {
        return $this->found($this->pages->findOneById($id));
    }
}
