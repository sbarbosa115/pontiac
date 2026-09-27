<?php

declare(strict_types=1);

namespace App\Booking;

use App\Api\ApiValidationException;
use App\Api\Input\AvailabilityInput;
use App\Entity\Availability;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Saves Agenda › Disponibilidad: weekly hours and exceptions checked (real times, "from" before "to", no two ranges of
 * one day overlapping), every problem reported where it is.
 */
final class AvailabilityEditor
{
    private const TIME = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function apply(Availability $availability, AvailabilityInput $input): Availability
    {
        $violations = [];
        $rules = self::rules($input->weeklyRules ?? [], $violations);
        $exceptions = self::exceptions($input->exceptions ?? [], $violations);
        if ([] !== $violations) {
            throw new ApiValidationException($violations);
        }

        $availability->change(
            $rules,
            $exceptions,
            (int) $input->bufferMinutes,
            (int) $input->minNoticeHours,
            (int) $input->bookingWindowDays,
            (int) $input->clientCancelHours,
            array_map(intval(...), $input->reminderHours ?? []),
            trim((string) $input->meetingLink),
        );
        $this->em->flush();

        return $availability;
    }

    /**
     * @param array<mixed>                                  $given
     * @param list<array{field: string, message: string}> $violations
     *
     * @return list<array{weekday: int, from: string, to: string}>
     */
    private static function rules(array $given, array &$violations): array
    {
        $rules = [];
        foreach (array_values($given) as $i => $rule) {
            $rule = \is_array($rule) ? $rule : [];
            $weekday = $rule['weekday'] ?? null;
            if (!\is_int($weekday) || $weekday < 1 || $weekday > 7) {
                self::fail($violations, "weeklyRules[$i].weekday", 'Choose a day of the week.');
                continue;
            }
            $range = self::range($rule, "weeklyRules[$i]", $violations);
            if (null === $range) {
                continue;
            }
            $rules[] = ['weekday' => $weekday, 'from' => $range[0], 'to' => $range[1]];
        }
        foreach (range(1, 7) as $weekday) {
            self::checkOverlaps(array_filter($rules, static fn (array $r) => $r['weekday'] === $weekday), 'weeklyRules', $violations);
        }

        return $rules;
    }

    /**
     * @param array<mixed>                                  $given
     * @param list<array{field: string, message: string}> $violations
     *
     * @return list<array{date: string, from: string|null, to: string|null}>
     */
    private static function exceptions(array $given, array &$violations): array
    {
        $exceptions = [];
        foreach (array_values($given) as $i => $exception) {
            $exception = \is_array($exception) ? $exception : [];
            $date = \is_string($exception['date'] ?? null) ? $exception['date'] : '';
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (false === $parsed || $parsed->format('Y-m-d') !== $date) {
                self::fail($violations, "exceptions[$i].date", 'Enter a real date.');
                continue;
            }
            if (null === ($exception['from'] ?? null) && null === ($exception['to'] ?? null)) {
                $exceptions[] = ['date' => $date, 'from' => null, 'to' => null];
                continue;
            }
            $range = self::range($exception, "exceptions[$i]", $violations);
            if (null !== $range) {
                $exceptions[] = ['date' => $date, 'from' => $range[0], 'to' => $range[1]];
            }
        }

        return $exceptions;
    }

    /**
     * @param array<mixed>                                  $given
     * @param list<array{field: string, message: string}> $violations
     *
     * @return array{0: string, 1: string}|null
     */
    private static function range(array $given, string $path, array &$violations): ?array
    {
        $from = \is_string($given['from'] ?? null) ? $given['from'] : '';
        $to = \is_string($given['to'] ?? null) ? $given['to'] : '';
        $ok = true;
        if (1 !== preg_match(self::TIME, $from)) {
            self::fail($violations, "$path.from", 'Write the time as HH:MM, e.g. 09:00.');
            $ok = false;
        }
        if (1 !== preg_match(self::TIME, $to)) {
            self::fail($violations, "$path.to", 'Write the time as HH:MM, e.g. 09:00.');
            $ok = false;
        }
        if ($ok && $from >= $to) {
            self::fail($violations, "$path.to", 'The end must be after the start.');
            $ok = false;
        }

        return $ok ? [$from, $to] : null;
    }

    /**
     * @param array<int, array{weekday: int, from: string, to: string}> $ranges     one day's
     * @param list<array{field: string, message: string}>              $violations
     */
    private static function checkOverlaps(array $ranges, string $field, array &$violations): void
    {
        usort($ranges, static fn (array $a, array $b) => $a['from'] <=> $b['from']);
        for ($i = 1; $i < \count($ranges); ++$i) {
            if ($ranges[$i]['from'] < $ranges[$i - 1]['to']) {
                self::fail($violations, $field, 'Two ranges of the same day overlap.');

                return;
            }
        }
    }

    /** @param list<array{field: string, message: string}> $violations */
    private static function fail(array &$violations, string $field, string $message): void
    {
        $violations[] = ['field' => $field, 'message' => $message];
    }
}
