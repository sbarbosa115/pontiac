<?php

declare(strict_types=1);

namespace App\Booking;

use App\Doctrine\AccountContext;
use App\Entity\Account;
use App\Entity\BookingSession;
use App\Enum\AccountFeature;
use App\Mail\BookingMailer;
use App\Repository\AccountRepository;
use App\Repository\AvailabilityRepository;
use App\Repository\BookingSessionRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Sends the reminders that are due, account by account (entering each: a command reaches a consultant's data only
 * that way). Each reminder is claimed first — a row in session_reminder with a unique (session, hours) — and only the
 * run that claimed it queues the emails, so two overlapping runs never send one twice.
 *
 * The claim is one INSERT … ON DUPLICATE KEY UPDATE (MySQL and MariaDB): 1 row means this run claimed it, 0 that it
 * was claimed already. A write naming the account explicitly: the account filter guards reads, not this.
 */
final class ReminderSender
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AccountRepository $accounts,
        private readonly AvailabilityRepository $availability,
        private readonly BookingSessionRepository $sessions,
        private readonly AccountContext $context,
        private readonly BookingMailer $mailer,
    ) {
    }

    /**
     * @return int how many reminders were sent
     */
    public function sendDue(\DateTimeImmutable $now = new \DateTimeImmutable()): int
    {
        $sent = 0;
        foreach ($this->accounts->findBy(['active' => true]) as $account) {
            if (!$account->hasFeature(AccountFeature::Booking)) {
                continue;
            }
            $sent += $this->sendFor($account, $now);
        }
        $this->context->reset();

        return $sent;
    }

    private function sendFor(Account $account, \DateTimeImmutable $now): int
    {
        $this->context->enterAccount($account);
        $sent = 0;
        foreach ($this->availability->forAccount($account)->getReminderHours() as $hours) {
            foreach ($this->sessions->findDueForReminder($hours, $now) as $session) {
                if (!$this->claim($account, $session, $hours, $now)) {
                    continue;
                }
                $this->mailer->reminder($account, $session, $hours);
                ++$sent;
            }
        }

        return $sent;
    }

    private function claim(Account $account, BookingSession $session, int $hours, \DateTimeImmutable $now): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            'INSERT INTO session_reminder (id, account_id, session_id, hours, sent_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE hours = hours',
            [Uuid::v7()->toBinary(), $account->getId()->toBinary(), $session->getId()->toBinary(), $hours, $now->format('Y-m-d H:i:s')],
        );
    }
}
