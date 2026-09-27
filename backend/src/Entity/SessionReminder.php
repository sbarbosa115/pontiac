<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * "The <hours>-hour reminder of this session went out." Inserted before the emails are queued: the unique key makes
 * a second sender (an overlapping cron run) fail to claim it, so no reminder goes out twice.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_reminder_session_hours', columns: ['session_id', 'hours'])]
class SessionReminder implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    #[ORM\ManyToOne(targetEntity: BookingSession::class)]
    #[ORM\JoinColumn(nullable: false)]
    private BookingSession $session;

    #[ORM\Column]
    private int $hours;

    #[ORM\Column]
    private \DateTimeImmutable $sentAt;

    public function __construct(BookingSession $session, int $hours)
    {
        $this->id = Uuid::v7();
        $this->setAccount($session->getAccount() ?? throw new \LogicException('A session belongs to an account.'));
        $this->session = $session;
        $this->hours = $hours;
        $this->sentAt = new \DateTimeImmutable();
    }
}
