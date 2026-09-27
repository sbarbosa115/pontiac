<?php

declare(strict_types=1);

namespace App\Tests\Functional\Booking;

use App\Booking\SlotFinder;
use App\Doctrine\AccountContext;
use App\Entity\Account;
use App\Entity\Availability;
use App\Entity\BookingSession;
use App\Entity\PlatformSettings;
use App\Tests\Functional\Api\ApiTestCase;

/**
 * The free slots a visitor sees: the consultant's hours in their own timezone (Bogotá, UTC−5), minus days off, the
 * minimum notice, and the sessions already booked with the buffer around them. The clock is fixed on Monday
 * 2026-10-05, 07:00 in Bogotá.
 */
final class SlotFinderTest extends ApiTestCase
{
    private const MONDAY_7AM_BOGOTA = '2026-10-05T12:00:00+00:00';

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
    }

    public function testTheWeeklyHoursAreTheConsultantsLocalHours(): void
    {
        $availability = $this->availability([['weekday' => 1, 'from' => '09:00', 'to' => '12:00']]);

        self::assertSame(['14:00', '15:00', '16:00'], $this->slots($availability, 60));
    }

    public function testASessionEndsWithinTheRange(): void
    {
        // 09:00–11:00 with 45-minute sessions: 09:00, 09:45; a third (10:30) would end at 11:15.
        $availability = $this->availability([['weekday' => 1, 'from' => '09:00', 'to' => '11:00']]);

        self::assertSame(['14:00', '14:45'], $this->slots($availability, 45));
    }

    public function testTheMinimumNoticeHidesWhatIsTooSoon(): void
    {
        $availability = $this->availability([['weekday' => 1, 'from' => '09:00', 'to' => '12:00']], notice: 3);

        // Now is 07:00: three hours of notice leaves 10:00 and 11:00.
        self::assertSame(['15:00', '16:00'], $this->slots($availability, 60));
    }

    public function testTheWindowReachesThatManyDaysAhead(): void
    {
        $availability = $this->availability([['weekday' => 1, 'from' => '09:00', 'to' => '10:00'], ['weekday' => 2, 'from' => '09:00', 'to' => '10:00'], ['weekday' => 3, 'from' => '09:00', 'to' => '10:00']], window: 1);

        self::assertSame(['2026-10-05 14:00', '2026-10-06 14:00'], $this->slots($availability, 60, withDate: true));
    }

    public function testAnExceptionIsADayOffOrOtherHoursThatDay(): void
    {
        $rules = [['weekday' => 1, 'from' => '09:00', 'to' => '12:00']];

        self::assertSame([], $this->slots($this->availability($rules, [['date' => '2026-10-05', 'from' => null, 'to' => null]]), 60));
        self::assertSame(['20:00'], $this->slots($this->availability($rules, [['date' => '2026-10-05', 'from' => '15:00', 'to' => '16:00']]), 60));
        self::assertSame(['14:00', '15:00', '16:00'], $this->slots($this->availability($rules, [['date' => '2026-10-06', 'from' => null, 'to' => null]]), 60), 'another day\'s exception changes nothing');
    }

    public function testABookedSessionAndItsBufferAreTaken(): void
    {
        // 09:00–13:00, 60 minutes plus 15 of buffer: 09:00, 10:15, 11:30. One is booked at 10:15.
        $availability = $this->availability([['weekday' => 1, 'from' => '09:00', 'to' => '13:00']], buffer: 15);
        [$booked] = $this->bookSession($this->createContact($this->account), $this->createPlan($this->account, durationMinutes: 60), new \DateTimeImmutable('2026-10-05T15:15:00+00:00'));

        self::assertSame(['14:00', '16:30'], $this->slots($availability, 60));
        self::assertSame(['14:00', '15:15', '16:30'], $this->slots($availability, 60, moving: $booked), 'a session being moved does not block itself');

        $this->save($booked->cancel('No puede'));
        self::assertSame(['14:00', '15:15', '16:30'], $this->slots($availability, 60), 'a cancelled session frees its time');
    }

    public function testAnotherConsultantsSessionsDoNotBlock(): void
    {
        $other = $this->createAccount('Plata Sana');
        $this->bookSession($this->createContact($other), $this->createPlan($other, durationMinutes: 60), new \DateTimeImmutable('2026-10-05T15:00:00+00:00'));

        self::assertSame(['14:00', '15:00', '16:00'], $this->slots($this->availability([['weekday' => 1, 'from' => '09:00', 'to' => '12:00']]), 60));
    }

    /**
     * @param list<array{weekday: int, from: string, to: string}>          $rules
     * @param list<array{date: string, from: string|null, to: string|null}> $exceptions
     */
    private function availability(array $rules, array $exceptions = [], int $buffer = 0, int $notice = 0, int $window = 0): Availability
    {
        return (new Availability($this->account, new PlatformSettings()))->change($rules, $exceptions, $buffer, $notice, $window, 24, [24], '');
    }

    /**
     * @return list<string> the slots in UTC, as H:i (or Y-m-d H:i)
     */
    private function slots(Availability $availability, int $duration, bool $withDate = false, ?BookingSession $moving = null): array
    {
        static::getContainer()->get(AccountContext::class)->enterAccount($this->account);
        $slots = static::getContainer()->get(SlotFinder::class)->slots($this->account, $availability, $duration, new \DateTimeImmutable(self::MONDAY_7AM_BOGOTA), $moving);

        return array_map(static fn (\DateTimeImmutable $slot) => $slot->format($withDate ? 'Y-m-d H:i' : 'H:i'), $slots);
    }
}
