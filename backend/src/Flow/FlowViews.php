<?php

declare(strict_types=1);

namespace App\Flow;

use App\Api\Output\BoardCardOutput;
use App\Api\Output\BoardColumnOutput;
use App\Api\Output\BoardOutput;
use App\Api\Output\ContactFlowOutput;
use App\Api\Output\FlowOutput;
use App\Api\Output\FlowStageOutput;
use App\Api\Output\FlowSummaryOutput;
use App\Api\Output\FlowTransitionOutput;
use App\Api\Output\OverdueOutput;
use App\Api\Output\StageRefOutput;
use App\Api\Presenter;
use App\Entity\Contact;
use App\Entity\ContactFlowState;
use App\Entity\Flow;
use App\Entity\FlowStage;
use App\Entity\LandingPage;
use App\Enum\PageStatus;
use App\Repository\ContactFlowStateRepository;
use App\Repository\LandingPageRepository;

/** What the flow screens show: the list, the editor, the board, a person's flows, who waits too long. */
final class FlowViews
{
    public function __construct(
        private readonly ContactFlowStateRepository $states,
        private readonly LandingPageRepository $pages,
    ) {
    }

    /**
     * @param list<Flow> $flows
     *
     * @return list<FlowSummaryOutput>
     */
    public function summaries(array $flows): array
    {
        $people = $this->states->countByFlow();

        return array_map(static fn (Flow $f) => new FlowSummaryOutput(
            id: (string) $f->getId(),
            name: $f->getName(),
            active: $f->isActive(),
            stages: \count($f->getStages()),
            people: $people[(string) $f->getId()] ?? 0,
        ), $flows);
    }

    public function flow(Flow $flow): FlowOutput
    {
        $people = [];
        foreach ($this->states->findForFlow($flow) as $state) {
            $key = (string) $state->getStage()->getId();
            $people[$key] = ($people[$key] ?? 0) + 1;
        }
        $pages = array_values(array_filter(
            $this->pages->findAllForPickers(),
            static fn (LandingPage $p) => PageStatus::Published === $p->getStatus() && ($p->getPublished()['settings']['flowId'] ?? null) === (string) $flow->getId(),
        ));

        return new FlowOutput(
            id: (string) $flow->getId(),
            name: $flow->getName(),
            active: $flow->isActive(),
            stages: array_map(static fn (FlowStage $s) => new FlowStageOutput(
                id: (string) $s->getId(),
                name: $s->getName(),
                kind: $s->getKind()->value,
                x: $s->getX(),
                y: $s->getY(),
                emailTemplateId: null === $s->getEmailTemplate() ? null : (string) $s->getEmailTemplate()->getId(),
                alertDays: $s->getAlertDays(),
                people: $people[(string) $s->getId()] ?? 0,
            ), $flow->getStages()),
            transitions: array_map(static fn ($t) => new FlowTransitionOutput(
                from: (string) $t->getFromStage()->getId(),
                to: (string) $t->getToStage()->getId(),
                trigger: $t->getTrigger()->value,
            ), $flow->getTransitions()),
            pages: array_map(Presenter::pageRef(...), $pages),
        );
    }

    public function board(Flow $flow, \DateTimeImmutable $now = new \DateTimeImmutable()): BoardOutput
    {
        $cards = [];
        foreach ($this->states->findForFlow($flow) as $state) {
            $cards[(string) $state->getStage()->getId()][] = $this->card($state, $now);
        }

        return new BoardOutput(
            flowId: (string) $flow->getId(),
            flowName: $flow->getName(),
            columns: array_map(static fn (FlowStage $s) => new BoardColumnOutput(
                stageId: (string) $s->getId(),
                name: $s->getName(),
                kind: $s->getKind()->value,
                alertDays: $s->getAlertDays(),
                cards: $cards[(string) $s->getId()] ?? [],
            ), $flow->getStages()),
        );
    }

    /**
     * @return list<ContactFlowOutput>
     */
    public function ofContact(Contact $contact): array
    {
        return array_map(static fn (ContactFlowState $s) => new ContactFlowOutput(
            flowId: (string) $s->getFlow()->getId(),
            flowName: $s->getFlow()->getName(),
            stageId: (string) $s->getStage()->getId(),
            stageName: $s->getStage()->getName(),
            enteredAt: (string) Presenter::timestamp($s->getEnteredAt()),
            stages: array_map(static fn (FlowStage $st) => new StageRefOutput(id: (string) $st->getId(), name: $st->getName()), $s->getFlow()->getStages()),
        ), $this->states->findForContact($contact));
    }

    /**
     * @return list<OverdueOutput>
     */
    public function overdue(\DateTimeImmutable $now = new \DateTimeImmutable()): array
    {
        return array_map(static fn (ContactFlowState $s) => new OverdueOutput(
            contactId: (string) $s->getContact()->getId(),
            fullName: $s->getContact()->getFullName(),
            flowId: (string) $s->getFlow()->getId(),
            flowName: $s->getFlow()->getName(),
            stageName: $s->getStage()->getName(),
            days: self::days($s->getEnteredAt(), $now),
        ), $this->states->findOverdue($now));
    }

    private function card(ContactFlowState $state, \DateTimeImmutable $now): BoardCardOutput
    {
        $contact = $state->getContact();
        $days = self::days($state->getEnteredAt(), $now);
        $alert = $state->getStage()->getAlertDays();

        return new BoardCardOutput(
            contactId: (string) $contact->getId(),
            fullName: $contact->getFullName(),
            status: $contact->getStatus()->value,
            category: Presenter::categoryRef($contact->getCategory()),
            enteredAt: (string) Presenter::timestamp($state->getEnteredAt()),
            days: $days,
            overdue: null !== $alert && $days >= $alert,
        );
    }

    private static function days(\DateTimeImmutable $since, \DateTimeImmutable $now): int
    {
        return max(0, (int) floor(($now->getTimestamp() - $since->getTimestamp()) / 86400));
    }
}
