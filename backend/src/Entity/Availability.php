<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * When a consultant takes sessions (Agenda › Disponibilidad), one per account. Hours are local to the account's
 * timezone ("09:00"–"12:00"); weekdays are ISO (1 Monday … 7 Sunday). An exception replaces a date's weekly hours:
 * with no hours, the day is off.
 */
#[ORM\Entity(repositoryClass: AvailabilityRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_availability_account', columns: ['account_id'])]
class Availability implements AccountOwnedInterface
{
    use HasUuid;
    use AccountOwnedTrait;

    /** @var list<array{weekday: int, from: string, to: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $weeklyRules;

    /** @var list<array{date: string, from: string|null, to: string|null}> */
    #[ORM\Column(type: Types::JSON)]
    private array $exceptions = [];

    #[ORM\Column(type: Types::SMALLINT)]
    private int $bufferMinutes;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $minNoticeHours;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $bookingWindowDays;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $clientCancelHours;

    /** @var list<int> */
    #[ORM\Column(type: Types::JSON)]
    private array $reminderHours;

    // Where sessions happen unless said otherwise: a video-call link or an address.
    #[ORM\Column(length: 500)]
    private string $meetingLink = '';

    /**
     * Weekdays 9:00–12:00 and 14:00–18:00 until the consultant says otherwise; the rest from the platform's defaults.
     */
    public function __construct(Account $account, PlatformSettings $defaults)
    {
        $this->id = Uuid::v7();
        $this->setAccount($account);
        $this->weeklyRules = [];
        foreach (range(1, 5) as $weekday) {
            $this->weeklyRules[] = ['weekday' => $weekday, 'from' => '09:00', 'to' => '12:00'];
            $this->weeklyRules[] = ['weekday' => $weekday, 'from' => '14:00', 'to' => '18:00'];
        }
        $this->bufferMinutes = $defaults->getSessionBufferMinutes();
        $this->minNoticeHours = $defaults->getMinNoticeHours();
        $this->bookingWindowDays = $defaults->getBookingWindowDays();
        $this->clientCancelHours = $defaults->getClientCancelHours();
        $this->reminderHours = $defaults->getReminderHours();
    }

    /**
     * @param list<array{weekday: int, from: string, to: string}>          $weeklyRules
     * @param list<array{date: string, from: string|null, to: string|null}> $exceptions
     * @param list<int>                                                   $reminderHours
     */
    public function change(array $weeklyRules, array $exceptions, int $bufferMinutes, int $minNoticeHours, int $bookingWindowDays, int $clientCancelHours, array $reminderHours, string $meetingLink): static
    {
        usort($weeklyRules, static fn (array $a, array $b) => [$a['weekday'], $a['from']] <=> [$b['weekday'], $b['from']]);
        usort($exceptions, static fn (array $a, array $b) => [$a['date'], $a['from'] ?? ''] <=> [$b['date'], $b['from'] ?? '']);
        $hours = array_values(array_unique($reminderHours));
        rsort($hours);
        $this->weeklyRules = $weeklyRules;
        $this->exceptions = $exceptions;
        $this->bufferMinutes = $bufferMinutes;
        $this->minNoticeHours = $minNoticeHours;
        $this->bookingWindowDays = $bookingWindowDays;
        $this->clientCancelHours = $clientCancelHours;
        $this->reminderHours = $hours;
        $this->meetingLink = $meetingLink;

        return $this;
    }

    /**
     * @return list<array{weekday: int, from: string, to: string}>
     */
    public function getWeeklyRules(): array
    {
        return $this->weeklyRules;
    }

    /**
     * @return list<array{date: string, from: string|null, to: string|null}>
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    public function getBufferMinutes(): int
    {
        return $this->bufferMinutes;
    }

    public function getMinNoticeHours(): int
    {
        return $this->minNoticeHours;
    }

    public function getBookingWindowDays(): int
    {
        return $this->bookingWindowDays;
    }

    public function getClientCancelHours(): int
    {
        return $this->clientCancelHours;
    }

    /**
     * @return list<int>
     */
    public function getReminderHours(): array
    {
        return $this->reminderHours;
    }

    public function getMeetingLink(): string
    {
        return $this->meetingLink;
    }
}
