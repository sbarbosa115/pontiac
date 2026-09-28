<?php

declare(strict_types=1);

namespace App\Command;

use App\Booking\ReminderSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Queues the session reminders that are due. Run every minute: a cron line on the server, the "scheduler" service in
 * docker compose. Runs never overlap (a lock), and a reminder is sent once even if they did (ReminderSender).
 */
#[AsCommand(name: 'app:send-due-reminders', description: 'Queues the session reminders that are due.')]
final class SendDueRemindersCommand extends Command
{
    public function __construct(
        private readonly ReminderSender $sender,
        private readonly LockFactory $locks,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->locks->createLock('send-due-reminders', 300);
        if (!$lock->acquire()) {
            $output->writeln('Another run is sending reminders.', OutputInterface::VERBOSITY_VERBOSE);

            return Command::SUCCESS;
        }

        try {
            $sent = $this->sender->sendDue();
        } finally {
            $lock->release();
        }
        $output->writeln(sprintf('%d reminder(s) queued.', $sent), OutputInterface::VERBOSITY_VERBOSE);

        return Command::SUCCESS;
    }
}
