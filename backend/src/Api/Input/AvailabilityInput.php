<?php

declare(strict_types=1);

namespace App\Api\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * PUT /api/admin/availability: the whole availability. The rules and exceptions are checked by AvailabilityEditor
 * (times, overlaps), the numbers here.
 */
final class AvailabilityInput
{
    /** @var list<mixed>|null [{weekday: 1-7, from: "09:00", to: "12:00"}] */
    #[Assert\NotNull]
    public ?array $weeklyRules = null;

    /** @var list<mixed>|null [{date: "2026-12-24", from: null, to: null}] */
    #[Assert\NotNull]
    public ?array $exceptions = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 120)]
    public ?int $bufferMinutes = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 168)]
    public ?int $minNoticeHours = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 365)]
    public ?int $bookingWindowDays = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 168)]
    public ?int $clientCancelHours = null;

    /** @var list<int>|null */
    #[Assert\NotNull]
    #[Assert\Count(min: 1, max: 3, minMessage: 'Keep at least one reminder.', maxMessage: 'At most three reminders.')]
    #[Assert\All([new Assert\Type('int'), new Assert\Range(min: 1, max: 168)])]
    public ?array $reminderHours = null;

    #[Assert\NotNull]
    #[Assert\Length(max: 500)]
    public ?string $meetingLink = null;
}
