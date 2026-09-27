<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Account;
use App\Entity\Availability;
use App\Entity\BookingSession;
use App\Repository\BookingSessionRepository;

/**
 * The free slots of a consultant: their weekly hours (or a date's exception), every `duration + buffer` minutes, from
 * now + the minimum notice up to the end of the booking window, without the slots that would come within the buffer
 * of a scheduled session. Hours are local to the account's timezone; slots come back in UTC.
 */
final class SlotFinder
{
    public function __construct(private readonly BookingSessionRepository $sessions)
    {
    }

    /**
     * @param BookingSession|null $moving a session being rescheduled: its own time is free for it
     *
     * @return list<\DateTimeImmutable> slot starts, UTC, soonest first
     */
    public function slots(Account $account, Availability $availability, int $durationMinutes, \DateTimeImmutable $now, ?BookingSession $moving = null): array
    {
        $zone = new \DateTimeZone($account->getTimezone());
        $today = $now->setTimezone($zone)->setTime(0, 0);
        $earliest = $now->modify(sprintf('+%d hours', $availability->getMinNoticeHours()));
        $last = $today->modify(sprintf('+%d days', $availability->getBookingWindowDays()));
        $step = $durationMinutes + $availability->getBufferMinutes();

        $busy = array_values(array_filter(
            $this->sessions->findScheduledBetween($now->modify('-1 day'), $last->modify('+1 day')),
            static fn (BookingSession $s) => null === $moving || !$s->getId()->equals($moving->getId()),
        ));

        $slots = [];
        for ($day = $today; $day <= $last; $day = $day->modify('+1 day')) {
            foreach ($this->hoursOn($availability, $day) as [$from, $to]) {
                $start = $this->at($day, $from, $zone);
                $end = $this->at($day, $to, $zone);
                for ($slot = $start; $slot->modify(sprintf('+%d minutes', $durationMinutes)) <= $end; $slot = $slot->modify(sprintf('+%d minutes', $step))) {
                    if ($slot >= $earliest && !$this->collides($slot, $durationMinutes, $availability->getBufferMinutes(), $busy)) {
                        $slots[] = $slot->setTimezone(new \DateTimeZone('UTC'));
                    }
                }
            }
        }

        return $slots;
    }

    /** Whether this exact start is one of the free slots now. */
    public function isFree(Account $account, Availability $availability, int $durationMinutes, \DateTimeImmutable $startsAt, \DateTimeImmutable $now, ?BookingSession $moving = null): bool
    {
        foreach ($this->slots($account, $availability, $durationMinutes, $now, $moving) as $slot) {
            if ($slot == $startsAt) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{0: string, 1: string}> the [from, to] hours of that date
     */
    private function hoursOn(Availability $availability, \DateTimeImmutable $day): array
    {
        $date = $day->format('Y-m-d');
        $exceptions = array_values(array_filter($availability->getExceptions(), static fn (array $e) => $e['date'] === $date));
        if ([] !== $exceptions) {
            // An exception replaces the weekly hours; one without hours makes it a day off.
            foreach ($exceptions as $exception) {
                if (null === $exception['from'] || null === $exception['to']) {
                    return [];
                }
            }

            return array_map(static fn (array $e) => [(string) $e['from'], (string) $e['to']], $exceptions);
        }

        $weekday = (int) $day->format('N');

        return array_values(array_map(
            static fn (array $rule) => [$rule['from'], $rule['to']],
            array_filter($availability->getWeeklyRules(), static fn (array $rule) => $rule['weekday'] === $weekday),
        ));
    }

    private function at(\DateTimeImmutable $day, string $time, \DateTimeZone $zone): \DateTimeImmutable
    {
        [$hour, $minute] = array_map(intval(...), explode(':', $time));

        return (new \DateTimeImmutable($day->format('Y-m-d'), $zone))->setTime($hour, $minute);
    }

    /**
     * @param list<BookingSession> $busy
     */
    private function collides(\DateTimeImmutable $start, int $durationMinutes, int $bufferMinutes, array $busy): bool
    {
        $end = $start->modify(sprintf('+%d minutes', $durationMinutes));
        foreach ($busy as $session) {
            if ($start < $session->getEndsAt()->modify(sprintf('+%d minutes', $bufferMinutes)) && $end > $session->getStartsAt()->modify(sprintf('-%d minutes', $bufferMinutes))) {
                return true;
            }
        }

        return false;
    }
}
