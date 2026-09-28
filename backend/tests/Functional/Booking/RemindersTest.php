<?php

declare(strict_types=1);

namespace App\Tests\Functional\Booking;

use App\Booking\ReminderSender;
use App\Enum\AccountFeature;
use App\Tests\Functional\Api\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

/**
 * Before each session, at each of the consultant's reminder hours (24 and 1 by default), the person and the consultant
 * get a reminder — once, however often the cron runs, and not for a time that had already passed when it was booked.
 */
final class RemindersTest extends ApiTestCase
{
    public function testEachReminderGoesOnceToBothSides(): void
    {
        $account = $this->createAccount();
        $this->createOwner($account);
        $startsAt = new \DateTimeImmutable('+3 days');
        $this->bookSession($this->createContact($account), $this->createPlan($account), $startsAt);

        self::assertSame(0, $this->sendDue($startsAt->modify('-30 hours')), 'nothing due yet');
        self::assertSame(1, $this->sendDue($startsAt->modify('-23 hours')));
        self::assertSame(
            ['laura@demo.test' => 'Recordatorio: tu sesión con Finanzas Claras es mañana', 'asesor@demo.test' => 'Recordatorio: sesión con Laura Gómez mañana'],
            $this->subjects(),
        );
        self::assertSame(0, $this->sendDue($startsAt->modify('-22 hours')), 'the cron runs again: already sent');
        self::assertSame(1, $this->sendDue($startsAt->modify('-50 minutes')));
        self::assertSame('Recordatorio: tu sesión con Finanzas Claras es en una hora', $this->subjects()['laura@demo.test']);
        self::assertSame(0, $this->sendDue($startsAt->modify('+5 minutes')), 'past sessions get nothing');
    }

    public function testATimeThatHadPassedWhenItWasBookedIsSkipped(): void
    {
        $account = $this->createAccount();
        $startsAt = new \DateTimeImmutable('+3 days');
        // Booked 20 hours before it starts: the 24-hour reminder never applies, the 1-hour one does.
        $this->bookSession($this->createContact($account), $this->createPlan($account), $startsAt, bookedAt: $startsAt->modify('-20 hours'));

        self::assertSame(0, $this->sendDue($startsAt->modify('-19 hours')));
        self::assertSame(1, $this->sendDue($startsAt->modify('-30 minutes')));
    }

    public function testCancelledSessionsAndConsultantsWithoutBookingGetNothing(): void
    {
        $account = $this->createAccount();
        $startsAt = new \DateTimeImmutable('+3 days');
        [$session] = $this->bookSession($this->createContact($account), $this->createPlan($account), $startsAt);
        $this->save($session->cancel(null));

        $other = $this->createAccount('Plata Sana');
        $this->save($other->setFeatures([AccountFeature::Portal]));
        $this->bookSession($this->createContact($other), $this->createPlan($other), $startsAt);

        self::assertSame(0, $this->sendDue($startsAt->modify('-23 hours')));
    }

    public function testTheCronCommandRuns(): void
    {
        $tester = new CommandTester((new Application(self::$kernel ?? self::bootKernel()))->find('app:send-due-reminders'));

        self::assertSame(0, $tester->execute([]));
    }

    private function sendDue(\DateTimeImmutable $now): int
    {
        return static::getContainer()->get(ReminderSender::class)->sendDue($now);
    }

    /**
     * The subject of the last email queued to each address.
     *
     * @return array<string, string>
     */
    private function subjects(): array
    {
        $subjects = [];
        foreach (self::getMailerMessages() as $email) {
            if ($email instanceof Email) {
                $subjects[$email->getTo()[0]->getAddress()] = (string) $email->getSubject();
            }
        }

        return $subjects;
    }
}
