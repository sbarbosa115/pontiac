<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Account;

/**
 * Free slots as a date picker draws them: the months from the first free day to the last, week by week from Monday,
 * each day open or not, and each open day's times. Days are the consultant's (their timezone and locale).
 */
final class SlotCalendar
{
    /**
     * @param iterable<\DateTimeImmutable> $slots in order
     *
     * @return array{
     *     months: list<array{label: string, weeks: list<list<array{date: string, number: int, open: bool}|null>>}>,
     *     days: array<string, array{label: string, slots: list<array{value: string, label: string}>}>,
     *     weekdays: list<array{short: string, long: string}>,
     * }
     */
    public static function build(Account $account, iterable $slots): array
    {
        $zone = new \DateTimeZone($account->getTimezone());
        $days = [];
        foreach ($slots as $slot) {
            $date = $slot->setTimezone($zone)->format('Y-m-d');
            $days[$date] ??= ['label' => SessionTime::date($account, $slot), 'slots' => []];
            $days[$date]['slots'][] = ['value' => $slot->format(\DATE_ATOM), 'label' => SessionTime::time($account, $slot)];
        }

        $months = [];
        if ([] !== $days) {
            $monthName = new \IntlDateFormatter($account->getLocale(), \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $zone, null, "LLLL 'de' y");
            $first = new \DateTimeImmutable(array_key_first($days).' 00:00', $zone);
            $last = new \DateTimeImmutable(array_key_last($days).' 00:00', $zone);
            for ($month = $first->modify('first day of this month'); $month <= $last; $month = $month->modify('first day of next month')) {
                $weeks = [];
                $week = array_fill(0, (int) $month->format('N') - 1, null);
                for ($day = $month; $day->format('m') === $month->format('m'); $day = $day->modify('+1 day')) {
                    $week[] = ['date' => $day->format('Y-m-d'), 'number' => (int) $day->format('j'), 'open' => isset($days[$day->format('Y-m-d')])];
                    if (7 === \count($week)) {
                        $weeks[] = $week;
                        $week = [];
                    }
                }
                if ([] !== $week) {
                    $weeks[] = array_pad($week, 7, null);
                }
                $label = (string) $monthName->format($month);
                $months[] = ['label' => mb_strtoupper(mb_substr($label, 0, 1)).mb_substr($label, 1), 'weeks' => $weeks];
            }
        }

        // Monday first, as the weeks above.
        $long = new \IntlDateFormatter($account->getLocale(), \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $zone, null, 'EEEE');
        $short = new \IntlDateFormatter($account->getLocale(), \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $zone, null, 'EEEEE');
        $weekdays = [];
        for ($i = 0, $day = new \DateTimeImmutable('2024-01-01 12:00', $zone); $i < 7; ++$i, $day = $day->modify('+1 day')) {
            $weekdays[] = ['short' => mb_strtoupper((string) $short->format($day)), 'long' => (string) $long->format($day)];
        }

        return ['months' => $months, 'days' => $days, 'weekdays' => $weekdays];
    }
}
