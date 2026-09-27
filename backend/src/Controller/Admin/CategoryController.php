<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Input\LeadCategoryInput;
use App\Api\InputMapper;
use App\Api\Output\LeadCategoryOutput;
use App\Api\Presenter;
use App\Entity\LeadCategory;
use App\Repository\LeadCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** Ajustes › Categorías: how the consultant sorts their prospectos. */
#[Route('/api/admin/categories', name: 'api_admin_category_')]
final class CategoryController extends ApiController
{
    public function __construct(
        private readonly LeadCategoryRepository $categories,
        private readonly InputMapper $input,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** ?q= searches the name; ?includeInactive=1 shows disabled ones too. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(LeadCategoryOutput::class, page: true)]
    public function list(Request $request): JsonResponse
    {
        $page = $this->categories->search($request->query->getString('q'), $request->query->getBoolean('includeInactive'), $this->pagination($request));

        return $this->page($page, Presenter::leadCategory(...));
    }

    /** Every category, for pickers (active first; a disabled one still shows where it is used). */
    #[Route('/all', name: 'all', methods: ['GET'])]
    #[ApiResponse(LeadCategoryOutput::class, list: true, key: 'items')]
    public function all(): JsonResponse
    {
        return $this->json(['items' => array_map(Presenter::leadCategory(...), $this->categories->findAllForPickers())]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[ApiResponse(LeadCategoryOutput::class, status: 201)]
    public function create(Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), LeadCategoryInput::class);
        $category = new LeadCategory($this->account(), trim((string) $data->name), (string) $data->color);
        $this->em->persist($category);
        $this->em->flush();

        return $this->json(Presenter::leadCategory($category), 201);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    #[ApiResponse(LeadCategoryOutput::class)]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), LeadCategoryInput::class);
        $category = $this->load($id)->rename(trim((string) $data->name), (string) $data->color);
        $this->em->flush();

        return $this->json(Presenter::leadCategory($category));
    }

    /** Not offered for new pages and contacts; the contacts that have it keep it. */
    #[Route('/{id}', name: 'disable', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[ApiResponse(LeadCategoryOutput::class)]
    public function disable(string $id): JsonResponse
    {
        $category = $this->load($id)->setActive(false);
        $this->em->flush();

        return $this->json(Presenter::leadCategory($category));
    }

    #[Route('/{id}/enable', name: 'enable', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(LeadCategoryOutput::class)]
    public function enable(string $id): JsonResponse
    {
        $category = $this->load($id)->setActive(true);
        $this->em->flush();

        return $this->json(Presenter::leadCategory($category));
    }

    private function load(string $id): LeadCategory
    {
        return $this->found($this->categories->findOneById($id));
    }
}
