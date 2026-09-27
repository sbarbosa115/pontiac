<?php

declare(strict_types=1);

namespace App\Flow;

use App\Api\ApiException;
use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\ContactFlowState;
use App\Entity\Flow;
use App\Entity\FlowEvent;
use App\Entity\FlowStage;
use App\Entity\User;
use App\Enum\AccountFeature;
use App\Enum\FlowTrigger;
use App\Repository\ContactFlowStateRepository;
use App\Repository\FlowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Moves people through flows: into a flow's start stage (from a page that feeds it, or by hand), along the arrow of
 * their current stage when something happens to them, or anywhere by hand on the board. Every move is kept
 * (FlowEvent), and entering a stage sends its email.
 */
final class FlowEngine
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FlowRepository $flows,
        private readonly ContactFlowStateRepository $states,
        private readonly FlowMailer $mailer,
    ) {
    }

    #[AsEventListener]
    public function onMoment(ContactMoment $moment): void
    {
        $account = $moment->account;
        if (!$account->hasFeature(AccountFeature::Flows) || $moment->contact->isAnonymized()) {
            return;
        }
        $now = new \DateTimeImmutable();
        $emails = [];

        $fed = $this->fedFlow($moment);
        if (null !== $fed && null === $this->states->findOneFor($moment->contact, $fed) && null !== $fed->startStage()) {
            $state = new ContactFlowState($moment->contact, $fed->startStage(), $now);
            $this->em->persist($state);
            $emails[] = $this->record($state, null, $fed->startStage(), FlowEvent::ADDED, null, $now);
            // Written now so the arrows below see it: booking from the page enters and moves on at once.
            $this->em->flush();
        }

        foreach ($this->states->findForContact($moment->contact) as $state) {
            if (!$state->getFlow()->isActive()) {
                continue;
            }
            $target = $this->follow($state->getFlow(), $state->getStage(), $moment->trigger);
            if (null !== $target) {
                $from = $state->getStage();
                $state->moveTo($target, $now);
                $emails[] = $this->record($state, $from, $target, $moment->trigger->value, null, $now);
            }
        }
        $this->em->flush();
        $this->send($account, $emails);
    }

    /** "Agregar a un flujo": into its start stage. */
    public function add(Account $account, Contact $contact, Flow $flow, User $by): ContactFlowState
    {
        $start = $flow->startStage();
        if (!$flow->isActive() || null === $start) {
            throw ApiException::conflict('flow_not_ready', 'This flow is disabled or has no start stage.');
        }
        if (null !== $this->states->findOneFor($contact, $flow)) {
            throw ApiException::conflict('already_in_flow', 'This person is already in this flow.');
        }
        $now = new \DateTimeImmutable();
        $state = new ContactFlowState($contact, $start, $now);
        $this->em->persist($state);
        $email = $this->record($state, null, $start, FlowEvent::ADDED, $by, $now);
        $this->em->flush();
        $this->send($account, [$email]);

        return $state;
    }

    /** A hand move on the board or the person's page: to any stage of the flow. */
    public function move(Account $account, ContactFlowState $state, FlowStage $to, User $by): ContactFlowState
    {
        if (!$to->getFlow()->getId()->equals($state->getFlow()->getId())) {
            throw ApiException::notFound();
        }
        if ($to->getId()->equals($state->getStage()->getId())) {
            return $state;
        }
        $now = new \DateTimeImmutable();
        $from = $state->getStage();
        $state->moveTo($to, $now);
        $email = $this->record($state, $from, $to, FlowEvent::MANUAL, $by, $now);
        $this->em->flush();
        $this->send($account, [$email]);

        return $state;
    }

    /** "Sacar del flujo": the history stays. */
    public function remove(ContactFlowState $state, User $by): void
    {
        $this->em->persist(new FlowEvent($state->getContact(), $state->getFlow(), $state->getStage(), null, FlowEvent::REMOVED, $by, new \DateTimeImmutable()));
        $this->em->remove($state);
        $this->em->flush();
    }

    /** The arrow leaving that stage with that trigger (one per stage and trigger, the editor makes sure). */
    public function follow(Flow $flow, FlowStage $from, FlowTrigger $trigger): ?FlowStage
    {
        if (FlowTrigger::Manual === $trigger) {
            return null;
        }
        foreach ($flow->getTransitions() as $transition) {
            if ($transition->getTrigger() === $trigger && $transition->getFromStage()->getId()->equals($from->getId())) {
                return $transition->getToStage();
            }
        }

        return null;
    }

    /** The flow a page feeds, when the moment came from a page with one (its published settings). */
    private function fedFlow(ContactMoment $moment): ?Flow
    {
        if (null === $moment->page || !\in_array($moment->trigger, [FlowTrigger::LeadSubmitted, FlowTrigger::SessionBooked], true)) {
            return null;
        }
        $id = $moment->page->getPublished()['settings']['flowId'] ?? null;
        $flow = \is_string($id) ? $this->flows->findOneById($id) : null;

        return null !== $flow && $flow->isActive() ? $flow : null;
    }

    /**
     * @return array{0: FlowEvent, 1: FlowStage}
     */
    private function record(ContactFlowState $state, ?FlowStage $from, FlowStage $to, string $reason, ?User $by, \DateTimeImmutable $now): array
    {
        $event = new FlowEvent($state->getContact(), $state->getFlow(), $from, $to, $reason, $by, $now);
        $this->em->persist($event);

        return [$event, $to];
    }

    /**
     * After the flush: each stage's email, noted on its move.
     *
     * @param list<array{0: FlowEvent, 1: FlowStage}> $moves
     */
    private function send(Account $account, array $moves): void
    {
        $sent = false;
        foreach ($moves as [$event, $stage]) {
            $subject = $this->mailer->stageEmail($account, $event->getContact(), $stage);
            if (null !== $subject) {
                $event->emailed($subject);
                $sent = true;
            }
        }
        if ($sent) {
            $this->em->flush();
        }
    }
}
