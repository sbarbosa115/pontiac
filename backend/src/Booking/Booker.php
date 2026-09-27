<?php

declare(strict_types=1);

namespace App\Booking;

use App\Api\ApiException;
use App\Entity\Account;
use App\Entity\BookingSession;
use App\Entity\Contact;
use App\Entity\Enrollment;
use App\Entity\LandingPage;
use App\Entity\Plan;
use App\Enum\EnrollmentStatus;
use App\Enum\SessionStatus;
use App\Mail\BookingMailer;
use App\Repository\AvailabilityRepository;
use App\Repository\BookingSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Books, moves and cancels sessions. A slot is taken under the account's booking lock, after checking again that it
 * is free: two visitors choosing the same slot at once get it once (409 slot_taken for the second).
 */
final class Booker
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SlotFinder $slots,
        private readonly AvailabilityRepository $availability,
        private readonly BookingSessionRepository $sessions,
        private readonly LockFactory $locks,
        private readonly BookingMailer $mailer,
    ) {
    }

    /**
     * A free plan's session, booked by a visitor from a page: a new enrollment for it, and the confirmation emails.
     */
    public function bookFromPage(Account $account, LandingPage $page, Plan $plan, Contact $contact, \DateTimeImmutable $startsAt): BookingSession
    {
        return $this->book($account, new Enrollment($contact, $plan, $page), $startsAt, BookingSession::BOOKED_BY_VISITOR);
    }

    /** A session the consultant books for a contact (a free plan: paid ones are booked after payment). */
    public function bookForContact(Account $account, Contact $contact, Plan $plan, \DateTimeImmutable $startsAt): BookingSession
    {
        if (!$plan->isFree() || !$plan->isActive()) {
            throw ApiException::unprocessable('plan_not_bookable', 'Only an active free plan is booked directly; a paid plan is booked after its payment.');
        }

        return $this->book($account, new Enrollment($contact, $plan, null), $startsAt, BookingSession::BOOKED_BY_STAFF);
    }

    /**
     * A session of a plan the person has (a paid one, once paid): while it is active and has sessions left. Booked by
     * the team, or by the client from the portal (then only at a free slot, like a visitor).
     */
    public function bookForEnrollment(Account $account, Enrollment $enrollment, \DateTimeImmutable $startsAt, string $bookedBy = BookingSession::BOOKED_BY_STAFF): BookingSession
    {
        if (EnrollmentStatus::Active !== $enrollment->getStatus()) {
            throw ApiException::conflict('enrollment_not_active', 'Only an active plan is booked: a paid one after its payment.');
        }
        $taken = $this->sessions->countsByEnrollment([$enrollment])[(string) $enrollment->getId()]['taken'];
        if ($taken >= $enrollment->getSessionsIncluded()) {
            throw ApiException::conflict('no_sessions_left', 'Every session of this plan is already booked or used.');
        }

        return $this->book($account, $enrollment, $startsAt, $bookedBy);
    }

    /**
     * @return string the session's new manage token (the emailed link changes: the old one stops working)
     */
    public function reschedule(Account $account, BookingSession $session, \DateTimeImmutable $startsAt, bool $byVisitor, \DateTimeImmutable $now = new \DateTimeImmutable()): string
    {
        $this->assertOpen($session);
        if ($byVisitor) {
            $this->assertVisitorMayChange($account, $session, $now);
        }

        $lock = $this->locks->createLock('booking-'.$account->getId());
        $lock->acquire(true);
        try {
            $availability = $this->availability->forAccount($account);
            // The visitor picks among the free slots; the consultant may put a session wherever they have room.
            $free = $byVisitor
                ? $this->slots->isFree($account, $availability, $session->getEnrollment()->getDurationMinutes(), $startsAt, $now, $session)
                : !$this->overlaps($session, $startsAt, $availability->getBufferMinutes());
            if (!$free) {
                throw ApiException::conflict('slot_taken', 'That time is no longer free. Choose another.');
            }
            $session->reschedule($startsAt);
            $token = $session->issueManageToken();
            $this->em->flush();
        } finally {
            $lock->release();
        }

        $this->mailer->rescheduled($account, $session, $token);

        return $token;
    }

    public function cancel(Account $account, BookingSession $session, ?string $reason, bool $byVisitor, \DateTimeImmutable $now = new \DateTimeImmutable()): BookingSession
    {
        $this->assertOpen($session);
        if ($byVisitor) {
            $this->assertVisitorMayChange($account, $session, $now);
        }
        $session->cancel($reason);
        $this->em->flush();
        $this->mailer->cancelled($account, $session, $byVisitor);

        return $session;
    }

    public function close(BookingSession $session, SessionStatus $outcome, \DateTimeImmutable $now = new \DateTimeImmutable()): BookingSession
    {
        try {
            $session->close($outcome, $now);
        } catch (\DomainException $e) {
            throw ApiException::conflict('session_not_closable', $e->getMessage());
        }
        $this->em->flush();
        $this->progress($session, $now);

        return $session;
    }

    public function reopen(BookingSession $session): BookingSession
    {
        try {
            $session->reopen();
        } catch (\DomainException $e) {
            throw ApiException::conflict('session_not_closed', $e->getMessage());
        }
        $this->em->flush();
        $this->progress($session, new \DateTimeImmutable());

        return $session;
    }

    /** Whether a visitor may still move or cancel it: up to the consultant's cancellation limit. */
    public function visitorMayChange(Account $account, BookingSession $session, \DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        $limit = $this->availability->forAccount($account)->getClientCancelHours();

        return SessionStatus::Scheduled === $session->getStatus() && $session->getStartsAt()->modify(sprintf('-%d hours', $limit)) > $now;
    }

    private function book(Account $account, Enrollment $enrollment, \DateTimeImmutable $startsAt, string $bookedBy): BookingSession
    {
        $lock = $this->locks->createLock('booking-'.$account->getId());
        $lock->acquire(true);
        try {
            $availability = $this->availability->forAccount($account);
            $free = BookingSession::BOOKED_BY_STAFF !== $bookedBy
                ? $this->slots->isFree($account, $availability, $enrollment->getDurationMinutes(), $startsAt, new \DateTimeImmutable())
                : !$this->overlapsAt($startsAt, $enrollment->getDurationMinutes(), $availability->getBufferMinutes());
            if (!$free) {
                throw ApiException::conflict('slot_taken', 'That time is no longer free. Choose another.');
            }
            [$session, $token] = BookingSession::book($enrollment, $startsAt, $availability->getMeetingLink(), $bookedBy);
            $this->em->persist($enrollment);
            $this->em->persist($session);
            $this->em->flush();
        } finally {
            $lock->release();
        }

        $this->mailer->confirmed($account, $session, $token);

        return $session;
    }

    /** Its plan completes when every session is used, and becomes active again when one is reopened. */
    private function progress(BookingSession $session, \DateTimeImmutable $now): void
    {
        $enrollment = $session->getEnrollment();
        $enrollment->progress($this->sessions->countsByEnrollment([$enrollment])[(string) $enrollment->getId()]['used'], $now);
        $this->em->flush();
    }

    private function overlaps(BookingSession $session, \DateTimeImmutable $startsAt, int $bufferMinutes): bool
    {
        $endsAt = $startsAt->modify(sprintf('+%d minutes', $session->getEnrollment()->getDurationMinutes()));

        return $this->sessions->overlaps($startsAt, $endsAt, $bufferMinutes, $session);
    }

    private function overlapsAt(\DateTimeImmutable $startsAt, int $durationMinutes, int $bufferMinutes): bool
    {
        return $this->sessions->overlaps($startsAt, $startsAt->modify(sprintf('+%d minutes', $durationMinutes)), $bufferMinutes);
    }

    private function assertOpen(BookingSession $session): void
    {
        if (SessionStatus::Scheduled !== $session->getStatus()) {
            throw ApiException::conflict('session_not_scheduled', 'This session is no longer scheduled.');
        }
    }

    private function assertVisitorMayChange(Account $account, BookingSession $session, \DateTimeImmutable $now): void
    {
        if (!$this->visitorMayChange($account, $session, $now)) {
            throw ApiException::conflict('too_late_to_change', 'This session can no longer be changed online. Write to the consultant.');
        }
    }
}
