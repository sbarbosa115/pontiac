<?php

declare(strict_types=1);

namespace App\Flow;

use App\Api\ApiValidationException;
use App\Api\Input\FlowSaveInput;
use App\Entity\Account;
use App\Entity\Flow;
use App\Entity\FlowStage;
use App\Entity\FlowTransition;
use App\Enum\FlowStageKind;
use App\Enum\FlowTrigger;
use App\Repository\ContactFlowStateRepository;
use App\Repository\EmailTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Saves the flow editor's canvas at once: stages (new, changed, removed) and every arrow. Checked as a whole, every
 * problem reported where it is: one start stage, names, real templates, one arrow per stage and event, and no stage
 * removed while people are in it.
 */
final class FlowGraph
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmailTemplateRepository $templates,
        private readonly ContactFlowStateRepository $states,
    ) {
    }

    /** A new flow, from the ready example: the common path of a consultancy. */
    public function starter(Account $account, string $name): Flow
    {
        $flow = new Flow($account, $name);
        $stages = [];
        // Top to bottom, the paid shortcut (Nuevo → Cliente) running down the right side.
        foreach ([['Nuevo', FlowStageKind::Start, 120, 0], ['Sesión agendada', FlowStageKind::Step, 0, 150], ['Seguimiento', FlowStageKind::Step, 0, 300], ['Cliente', FlowStageKind::Step, 240, 450], ['Finalizado', FlowStageKind::End, 240, 600]] as $i => [$stageName, $kind, $x, $y]) {
            $stage = (new FlowStage($flow))->change($stageName, $kind, $i, $x, $y, null, 'Seguimiento' === $stageName ? 7 : null);
            $flow->addStage($stage);
            $stages[] = $stage;
        }
        foreach ([[0, 1, FlowTrigger::SessionBooked], [1, 2, FlowTrigger::SessionDone], [2, 3, FlowTrigger::PaymentApproved], [0, 3, FlowTrigger::PaymentApproved], [3, 4, FlowTrigger::ConsultancyFinished]] as [$from, $to, $trigger]) {
            $flow->addTransition(new FlowTransition($flow, $stages[$from], $stages[$to], $trigger));
        }
        $this->em->persist($flow);
        $this->em->flush();

        return $flow;
    }

    public function save(Flow $flow, FlowSaveInput $input): Flow
    {
        $violations = [];
        $given = array_values(array_filter($input->stages ?? [], \is_array(...)));
        $templates = [];
        foreach ($this->templates->findAllForPickers() as $template) {
            $templates[(string) $template->getId()] = $template;
        }

        // Stages, by the id the canvas knows them by.
        $byId = [];
        $kept = [];
        $starts = 0;
        foreach ($given as $i => $data) {
            $key = \is_string($data['id'] ?? null) ? $data['id'] : '';
            $name = trim(\is_string($data['name'] ?? null) ? $data['name'] : '');
            $kind = FlowStageKind::tryFrom(\is_string($data['kind'] ?? null) ? $data['kind'] : '');
            $templateId = $data['emailTemplateId'] ?? null;
            $alert = $data['alertDays'] ?? null;
            if ('' === $name || mb_strlen($name) > 80) {
                $violations[] = ['field' => "stages[$i].name", 'message' => 'Name the stage (at most 80 characters).'];
            }
            if (null === $kind) {
                $violations[] = ['field' => "stages[$i].kind", 'message' => 'Choose whether the stage starts, continues or ends the flow.'];
            }
            if (FlowStageKind::Start === $kind) {
                ++$starts;
            }
            if (null !== $templateId && '' !== $templateId && !isset($templates[$templateId])) {
                $violations[] = ['field' => "stages[$i].emailTemplateId", 'message' => 'Choose one of your email templates.'];
            }
            if (null !== $alert && '' !== $alert && (!\is_int($alert) || $alert < 1 || $alert > 365)) {
                $violations[] = ['field' => "stages[$i].alertDays", 'message' => 'Alert after 1 to 365 days, or leave it empty.'];
            }
            $existing = Uuid::isValid($key) ? $flow->stage(Uuid::fromString($key)) : null;
            $stage = $existing ?? new FlowStage($flow);
            $byId[$key] = [$stage, $name, $kind, $i, (int) ($data['x'] ?? 0), (int) ($data['y'] ?? 0), \is_string($templateId) && isset($templates[$templateId]) ? $templates[$templateId] : null, \is_int($alert) ? $alert : null, null === $existing];
            if (null !== $existing) {
                $kept[(string) $existing->getId()] = true;
            }
        }
        if (1 !== $starts) {
            $violations[] = ['field' => 'stages', 'message' => 'A flow has exactly one start stage.'];
        }
        $removed = array_values(array_filter($flow->getStages(), static fn (FlowStage $s) => !isset($kept[(string) $s->getId()])));
        foreach ($removed as $stage) {
            if ($this->states->countInStage($stage) > 0) {
                $violations[] = ['field' => 'stages', 'message' => 'Move the people in "%stage%" to another stage before removing it.', 'parameters' => ['%stage%' => $stage->getName()]];
            }
        }

        // Arrows.
        $arrows = [];
        $seen = [];
        foreach (array_values(array_filter($input->transitions ?? [], \is_array(...))) as $i => $data) {
            $from = $byId[\is_string($data['from'] ?? null) ? $data['from'] : ''][0] ?? null;
            $to = $byId[\is_string($data['to'] ?? null) ? $data['to'] : ''][0] ?? null;
            $trigger = FlowTrigger::tryFrom(\is_string($data['trigger'] ?? null) ? $data['trigger'] : '');
            if (null === $from || null === $to || $from === $to) {
                $violations[] = ['field' => "transitions[$i]", 'message' => 'An arrow goes from one stage to another.'];
                continue;
            }
            if (null === $trigger) {
                $violations[] = ['field' => "transitions[$i].trigger", 'message' => 'Choose what moves people along this arrow.'];
                continue;
            }
            $key = spl_object_id($from).'|'.$trigger->value;
            if (FlowTrigger::Manual !== $trigger && isset($seen[$key])) {
                $violations[] = ['field' => "transitions[$i].trigger", 'message' => 'A stage has one arrow per event: the event would not know which to follow.'];
                continue;
            }
            $seen[$key] = true;
            $arrows[] = [$from, $to, $trigger];
        }
        if ([] !== $violations) {
            throw new ApiValidationException($violations);
        }

        $flow->rename(trim((string) $input->name));
        $flow->clearTransitions();
        // Arrows go first: a removed stage's arrows must not outlive it.
        $this->em->flush();
        foreach ($removed as $stage) {
            $flow->removeStage($stage);
        }
        foreach ($byId as [$stage, $name, $kind, $position, $x, $y, $template, $alert, $new]) {
            $stage->change($name, $kind ?? FlowStageKind::Step, $position, $x, $y, $template, $alert);
            if ($new) {
                $flow->addStage($stage);
            }
        }
        foreach ($arrows as [$from, $to, $trigger]) {
            $flow->addTransition(new FlowTransition($flow, $from, $to, $trigger));
        }
        $this->em->flush();

        return $flow;
    }
}
