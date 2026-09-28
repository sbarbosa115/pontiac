<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiException;
use App\Api\ApiResponse;
use App\Api\Input\FlowCreateInput;
use App\Api\Input\FlowMoveInput;
use App\Api\Input\FlowPersonInput;
use App\Api\Input\FlowSaveInput;
use App\Api\InputMapper;
use App\Api\Output\BoardOutput;
use App\Api\Output\ContactFlowOutput;
use App\Api\Output\FlowOutput;
use App\Api\Output\FlowSummaryOutput;
use App\Entity\Contact;
use App\Entity\ContactFlowState;
use App\Entity\Flow;
use App\Enum\AccountFeature;
use App\Flow\FlowEngine;
use App\Flow\FlowGraph;
use App\Flow\FlowViews;
use App\Repository\ContactFlowStateRepository;
use App\Repository\ContactRepository;
use App\Repository\FlowRepository;
use App\Security\RequiresFeature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * Flujos: the list, the editor (the whole canvas saved at once), the board, and people added, moved and taken out by
 * hand. The owner and the assistant alike.
 */
#[Route('/api/admin/flows', name: 'api_admin_flow_')]
#[RequiresFeature(AccountFeature::Flows)]
final class FlowController extends ApiController
{
    public function __construct(
        private readonly FlowRepository $flows,
        private readonly FlowViews $views,
        private readonly InputMapper $input,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** ?q= searches the name; ?includeInactive=1 shows disabled ones too. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[ApiResponse(FlowSummaryOutput::class, page: true)]
    public function list(Request $request): JsonResponse
    {
        $page = $this->flows->search($request->query->getString('q'), $request->query->getBoolean('includeInactive'), $this->pagination($request));
        // Counted for the whole page at once, then handed out row by row.
        $summaries = array_combine(array_map(static fn (Flow $f) => (string) $f->getId(), $page->items), $this->views->summaries($page->items));

        return $this->page($page, static fn (Flow $f) => $summaries[(string) $f->getId()]);
    }

    /** Every flow, active first: pickers. */
    #[Route('/all', name: 'all', methods: ['GET'])]
    #[ApiResponse(FlowSummaryOutput::class, list: true, key: 'items')]
    public function all(): JsonResponse
    {
        return $this->json(['items' => $this->views->summaries($this->flows->findAllWithStages())]);
    }

    /** "Nuevo flujo": from the ready example, to change in the editor. */
    #[Route('', name: 'create', methods: ['POST'])]
    #[ApiResponse(FlowOutput::class, status: 201)]
    public function create(Request $request, FlowGraph $graph): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), FlowCreateInput::class);

        return $this->json($this->views->flow($graph->starter($this->account(), trim((string) $data->name))), 201);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[ApiResponse(FlowOutput::class)]
    public function show(string $id): JsonResponse
    {
        return $this->json($this->views->flow($this->load($id)));
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    #[ApiResponse(FlowOutput::class)]
    public function update(string $id, Request $request, FlowGraph $graph): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), FlowSaveInput::class);
        $flow = $graph->save($this->load($id), $data);

        return $this->json($this->views->flow($this->load((string) $flow->getId())));
    }

    /** Disabled: nobody moves in it any more; its people stay where they are. */
    #[Route('/{id}', name: 'disable', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[ApiResponse(FlowOutput::class)]
    public function disable(string $id): JsonResponse
    {
        $flow = $this->load($id)->setActive(false);
        $this->em->flush();

        return $this->json($this->views->flow($flow));
    }

    #[Route('/{id}/enable', name: 'enable', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(FlowOutput::class)]
    public function enable(string $id): JsonResponse
    {
        $flow = $this->load($id)->setActive(true);
        $this->em->flush();

        return $this->json($this->views->flow($flow));
    }

    #[Route('/{id}/board', name: 'board', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[ApiResponse(BoardOutput::class)]
    public function board(string $id): JsonResponse
    {
        return $this->json($this->views->board($this->load($id)));
    }

    /** "Agregar a un flujo": into its start stage. Answers the person's flows. */
    #[Route('/{id}/people', name: 'add', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(ContactFlowOutput::class, list: true, key: 'items', status: 201)]
    public function add(string $id, Request $request, ContactRepository $contacts, FlowEngine $engine): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), FlowPersonInput::class);
        $contact = $this->found($contacts->findOneById((string) $data->contactId));
        if ($contact->isAnonymized()) {
            throw ApiException::conflict('already_anonymized', 'This person\'s data was erased.');
        }
        $engine->add($this->account(), $contact, $this->load($id), $this->appUser());

        return $this->json(['items' => $this->views->ofContact($contact)], 201);
    }

    /** A hand move (the board, the person's page): to any stage of the flow. */
    #[Route('/{id}/people/{contactId}/move', name: 'move', requirements: ['id' => Requirement::UUID, 'contactId' => Requirement::UUID], methods: ['POST'])]
    #[ApiResponse(ContactFlowOutput::class, list: true, key: 'items')]
    public function move(string $id, string $contactId, Request $request, ContactRepository $contacts, ContactFlowStateRepository $states, FlowEngine $engine): JsonResponse
    {
        $data = $this->input->map($this->input->json($request), FlowMoveInput::class);
        [$contact, $state] = $this->state($id, $contactId, $contacts, $states);
        $stageId = (string) $data->stageId;
        $stage = Uuid::isValid($stageId) ? $state->getFlow()->stage(Uuid::fromString($stageId)) : null;
        $engine->move($this->account(), $state, $stage ?? throw ApiException::notFound(), $this->appUser());

        return $this->json(['items' => $this->views->ofContact($contact)]);
    }

    /** "Sacar del flujo". */
    #[Route('/{id}/people/{contactId}', name: 'remove', requirements: ['id' => Requirement::UUID, 'contactId' => Requirement::UUID], methods: ['DELETE'])]
    #[ApiResponse(ContactFlowOutput::class, list: true, key: 'items')]
    public function remove(string $id, string $contactId, ContactRepository $contacts, ContactFlowStateRepository $states, FlowEngine $engine): JsonResponse
    {
        [$contact, $state] = $this->state($id, $contactId, $contacts, $states);
        $engine->remove($state, $this->appUser());

        return $this->json(['items' => $this->views->ofContact($contact)]);
    }

    private function load(string $id): Flow
    {
        return $this->found($this->flows->findOneWithGraph($id));
    }

    /**
     * @return array{0: Contact, 1: ContactFlowState}
     */
    private function state(string $flowId, string $contactId, ContactRepository $contacts, ContactFlowStateRepository $states): array
    {
        $contact = $this->found($contacts->findOneById($contactId));
        $state = $states->findOneFor($contact, $this->load($flowId)) ?? throw ApiException::notFound();

        return [$contact, $state];
    }
}
