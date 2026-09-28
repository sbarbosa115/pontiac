<?php

declare(strict_types=1);

namespace App\Tests\Unit\Booking;

use App\Booking\SlotCalendar;
use App\Entity\Account;
use PHPUnit\Framework\TestCase;

final class SlotCalendarTest extends TestCase
{
    public function testFreeSlotsBecomeTheMonthsWeeksAndDaysOfAPicker(): void
    {
        $account = new Account('Finanzas Claras', 'finanzas-claras');
        $bogota = new \DateTimeZone('America/Bogota');
        // 21:00 in Bogotá on the 30th is already October 1st in UTC: the day is the consultant's.
        $slots = [
            new \DateTimeImmutable('2026-09-30 09:00', $bogota),
            new \DateTimeImmutable('2026-09-30 21:00', $bogota),
            new \DateTimeImmutable('2026-10-02 10:00', $bogota),
        ];

        $calendar = SlotCalendar::build($account, $slots);

        self::assertSame(['2026-09-30', '2026-10-02'], array_keys($calendar['days']));
        // ICU writes "a. m." with no-break spaces.
        self::assertSame(['9:00 a. m.', '9:00 p. m.'], array_map(static fn (string $l) => str_replace(["\u{202F}", "\u{A0}"], ' ', $l), array_column($calendar['days']['2026-09-30']['slots'], 'label')));
        self::assertSame('miércoles, 30 de septiembre de 2026', $calendar['days']['2026-09-30']['label']);
        self::assertSame(['Septiembre de 2026', 'Octubre de 2026'], array_column($calendar['months'], 'label'));
        self::assertSame(['L', 'M', 'M', 'J', 'V', 'S', 'D'], array_column($calendar['weekdays'], 'short'));

        // September 2026 starts on a Tuesday: one empty Monday, then the 1st.
        $september = $calendar['months'][0]['weeks'];
        self::assertNull($september[0][0]);
        self::assertSame(1, $september[0][1]['number'] ?? null);
        $open = array_column(array_filter(array_merge(...$september)), 'open', 'date');
        self::assertSame(['2026-09-30'], array_keys(array_filter($open)));
        self::assertCount(7, $september[\count($september) - 1], 'the last week is padded to seven days');
    }

    public function testNoSlotsNoMonths(): void
    {
        self::assertSame([], SlotCalendar::build(new Account('Finanzas Claras', 'finanzas-claras'), [])['months']);
    }
}
