<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Input\PlanInput;
use App\Api\InputMapper;
use App\Api\Output\PlanOutput;
use App\Api\Presenter;
use App\Entity\Plan;
use App\Entity\User;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Planes: what the consultant sells. Everyone on the team reads them; only the owner sets prices. */
#[Route('/api/admin/plans', name: 'api_admin_plan_')]
final class PlanController extends ApiController
{
    public function __construct(
        private readonly PlanRepository $plans,
        private readonly InputMapper $input,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** ?q= searches name and description; ?includeInactive=1 shows disabled ones too. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(PlanOutput::class, page: true)]
    public function list(Request $request): JsonResponse
    {
        return $this->page($this->plans->search($request->query->getString('q'), $request->query->getBoolean('includeInactive'), $this->pagination($request)), Presenter::plan(...));
    }

    /** Every plan, for pickers (active first). */
    #[Route('/all', name: 'all', methods: ['GET'])]
    #[ApiResponse(PlanOutput::class, list: true, key: 'items')]
    public function all(): JsonResponse
    {
        return $this->json(['items' => array_map(Presenter::plan(...), $this->plans->findAllForPickers())]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(PlanOutput::class, status: 201)]
    public function create(Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), PlanInput::class);
        $plan = new Plan($this->account(), trim((string) $data->name), trim((string) $data->description), (string) $data->price, (int) $data->sessions, (int) $data->durationMinutes);
        $this->em->persist($plan);
        $this->em->flush();

        return $this->json(Presenter::plan($plan), 201);
    }

    /** Enrollments keep the name, price and sessions they were made with: a change applies from now on. */
    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(PlanOutput::class)]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), PlanInput::class);
        $plan = $this->load($id)->change(trim((string) $data->name), trim((string) $data->description), (string) $data->price, (int) $data->sessions, (int) $data->durationMinutes);
        $this->em->flush();

        return $this->json(Presenter::plan($plan));
    }

    /** No longer offered: pages stop booking it; enrollments already made keep their sessions. */
    #[Route('/{id}', name: 'disable', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(PlanOutput::class)]
    public function disable(string $id): JsonResponse
    {
        $plan = $this->load($id)->setActive(false);
        $this->em->flush();

        return $this->json(Presenter::plan($plan));
    }

    #[Route('/{id}/enable', name: 'enable', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(User::ROLE_OWNER)]
    #[ApiResponse(PlanOutput::class)]
    public function enable(string $id): JsonResponse
    {
        $plan = $this->load($id)->setActive(true);
        $this->em->flush();

        return $this->json(Presenter::plan($plan));
    }

    private function load(string $id): Plan
    {
        return $this->found($this->plans->findOneById($id));
    }
}
