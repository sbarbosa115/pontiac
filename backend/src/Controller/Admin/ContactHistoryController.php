<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Api\ApiController;
use App\Api\ApiResponse;
use App\Api\Output\HistoryItemOutput;
use App\Api\Presenter;
use App\Entity\FlowEvent;
use App\Entity\OutgoingEmail;
use App\Repository\ContactRepository;
use App\Repository\FlowEventRepository;
use App\Repository\OutgoingEmailRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** A person's Historial: every move in a flow and every email sent to them, the latest first. */
#[Route('/api/admin/contacts/{id}/history', name: 'api_admin_contact_history', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class ContactHistoryController extends ApiController
{
    #[ApiResponse(HistoryItemOutput::class, list: true, key: 'items')]
    public function __invoke(string $id, ContactRepository $contacts, FlowEventRepository $events, OutgoingEmailRepository $emails): JsonResponse
    {
        $contact = $this->found($contacts->findOneById($id));
        $items = [
            ...array_map(static fn (FlowEvent $e) => new HistoryItemOutput(
                type: 'flow',
                at: (string) Presenter::timestamp($e->getCreatedAt()),
                flowName: $e->getFlowName(),
                fromStage: $e->getFromStage(),
                toStage: $e->getToStage(),
                reason: $e->getReason(),
                by: $e->getByUser()?->getFullName(),
                subject: $e->getEmailSubject(),
                emailStatus: null,
            ), $events->findForContact($contact)),
            ...array_map(static fn (OutgoingEmail $e) => new HistoryItemOutput(
                type: 'email',
                at: (string) Presenter::timestamp($e->getSentAt()),
                flowName: null,
                fromStage: null,
                toStage: null,
                reason: $e->getKind(),
                by: null,
                subject: $e->getSubject(),
                emailStatus: $e->getStatus()->value,
            ), $contact->isAnonymized() ? [] : $emails->findForRecipient($this->account(), $contact->getEmail())),
        ];
        usort($items, static fn (HistoryItemOutput $a, HistoryItemOutput $b) => strcmp($b->at, $a->at));

        return $this->json(['items' => $items]);
    }
}
