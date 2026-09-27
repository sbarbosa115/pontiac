<?php

declare(strict_types=1);

namespace App\Mail;

use App\Booking\CalendarFile;
use App\Booking\SessionTime;
use App\Entity\Account;
use App\Entity\BookingSession;
use App\Entity\User;
use App\Repository\PlatformSettingsRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * The emails around a session, all queued and tagged: confirmation, rescheduled, cancelled and reminders, to the
 * contact (from the consultant, with a calendar file and the link to manage it) and to the consultant (owner).
 */
final class BookingMailer
{
    public const CONFIRMED = 'booking_confirmed';
    public const NEW_FOR_CONSULTANT = 'booking_new';
    public const RESCHEDULED = 'booking_rescheduled';
    public const CANCELLED = 'booking_cancelled';
    public const REMINDER = 'booking_reminder';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $users,
        private readonly PlatformSettingsRepository $settings,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    public function confirmed(Account $account, BookingSession $session, string $token): void
    {
        $this->toContact($account, $session, self::CONFIRMED, sprintf('Tu sesión con %s: %s', $account->getName(), SessionTime::dateTime($account, $session->getStartsAt())), $this->manageUrl($account, $token));
        $this->toConsultant($account, $session, self::NEW_FOR_CONSULTANT, sprintf('Nueva sesión agendada: %s', $session->getContact()->getFullName()));
    }

    public function rescheduled(Account $account, BookingSession $session, string $token): void
    {
        $this->toContact($account, $session, self::RESCHEDULED, sprintf('Tu sesión con %s cambió de hora', $account->getName()), $this->manageUrl($account, $token));
        $this->toConsultant($account, $session, self::RESCHEDULED, sprintf('Sesión reprogramada: %s', $session->getContact()->getFullName()));
    }

    public function cancelled(Account $account, BookingSession $session, bool $byVisitor): void
    {
        $this->toContact($account, $session, self::CANCELLED, sprintf('Tu sesión con %s fue cancelada', $account->getName()), null, cancelled: true);
        $this->toConsultant($account, $session, self::CANCELLED, sprintf('Sesión cancelada%s: %s', $byVisitor ? ' por el cliente' : '', $session->getContact()->getFullName()));
    }

    public function reminder(Account $account, BookingSession $session, int $hours): void
    {
        $in = 1 === $hours ? 'en una hora' : ($hours < 24 ? sprintf('en %d horas', $hours) : ($hours === 24 ? 'mañana' : sprintf('en %d días', intdiv($hours, 24))));
        $this->toContact($account, $session, self::REMINDER, sprintf('Recordatorio: tu sesión con %s es %s', $account->getName(), $in), null, extra: ['in' => $in]);
        $this->toConsultant($account, $session, self::REMINDER, sprintf('Recordatorio: sesión con %s %s', $session->getContact()->getFullName(), $in), extra: ['in' => $in]);
    }

    /**
     * @param array<string, string> $extra
     */
    private function toContact(Account $account, BookingSession $session, string $kind, string $subject, ?string $manageUrl, bool $cancelled = false, array $extra = []): void
    {
        $contact = $session->getContact();
        if ($contact->isAnonymized()) {
            return;
        }
        $owner = $this->owner($account);
        $email = (new TemplatedEmail())
            ->from(new Address($this->from, $account->getName()))
            ->to(new Address($contact->getEmail(), $contact->getFullName()))
            ->subject($subject)
            ->htmlTemplate('emails/booking.html.twig')
            ->textTemplate('emails/booking.txt.twig')
            ->context($this->context($account, $session, $kind, 'contact', $manageUrl, $extra))
            ->attach(CalendarFile::for($session, sprintf('Sesión con %s', $account->getName()), $account->getName(), null === $manageUrl ? '' : 'Cambiar o cancelar: '.$manageUrl, $cancelled), 'sesion.ics', 'text/calendar; charset=UTF-8; method='.($cancelled ? 'CANCEL' : 'PUBLISH'));
        if (null !== $owner) {
            $email->replyTo(new Address($owner->getEmail(), $owner->getFullName()));
        }

        $this->mailer->send(EmailTag::apply($email, $kind, $account));
    }

    /**
     * @param array<string, string> $extra
     */
    private function toConsultant(Account $account, BookingSession $session, string $kind, string $subject, array $extra = []): void
    {
        $owner = $this->owner($account);
        if (null === $owner) {
            return;
        }
        $contact = $session->getContact();
        $email = (new TemplatedEmail())
            ->from(new Address($this->from, $this->settings->current()->getSenderName()))
            ->to(new Address($owner->getEmail(), $owner->getFullName()))
            ->subject($subject)
            ->htmlTemplate('emails/booking.html.twig')
            ->textTemplate('emails/booking.txt.twig')
            ->context($this->context($account, $session, $kind, 'consultant', null, $extra + ['contactUrl' => rtrim($this->appUrl, '/').'/admin/prospectos/'.$contact->getId()]))
            ->attach(CalendarFile::for($session, sprintf('Sesión con %s', $contact->getFullName()), $account->getName(), '', self::CANCELLED === $kind), 'sesion.ics', 'text/calendar; charset=UTF-8');
        if (!$contact->isAnonymized()) {
            $email->replyTo(new Address($contact->getEmail(), $contact->getFullName()));
        }

        $this->mailer->send(EmailTag::apply($email, $kind, $account));
    }

    /**
     * @param array<string, string> $extra
     *
     * @return array<string, mixed>
     */
    private function context(Account $account, BookingSession $session, string $kind, string $audience, ?string $manageUrl, array $extra): array
    {
        return $extra + [
            'locale' => $account->getLocale(),
            'sender' => 'contact' === $audience ? $account->getName() : null,
            'kind' => $kind,
            'audience' => $audience,
            'consultant' => $account->getName(),
            'contactName' => $session->getContact()->getFullName(),
            'contactEmail' => $session->getContact()->getEmail(),
            'plan' => $session->getEnrollment()->getPlanName(),
            'date' => SessionTime::date($account, $session->getStartsAt()),
            'time' => SessionTime::time($account, $session->getStartsAt()),
            'minutes' => $session->getEnrollment()->getDurationMinutes(),
            'meetingLink' => $session->getMeetingLink(),
            'cancelReason' => $session->getCancelReason(),
            'manageUrl' => $manageUrl,
        ];
    }

    private function owner(Account $account): ?User
    {
        $owner = $this->users->findOwnersOf([$account])[(string) $account->getId()] ?? null;

        return null !== $owner && $owner->isActive() ? $owner : null;
    }

    private function manageUrl(Account $account, string $token): string
    {
        return rtrim($this->appUrl, '/').'/'.$account->getSlug().'/reservar/'.$token;
    }
}
