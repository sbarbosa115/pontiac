<?php

declare(strict_types=1);

namespace App\Mail;

use App\Booking\SessionTime;
use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\Enrollment;
use App\Entity\Payment;
use App\Entity\User;
use App\Payment\MoneyText;
use App\Payment\PaymentLinks;
use App\Repository\PlatformSettingsRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * The emails about money, queued and tagged: a plan's payment link and the receipt to the person, "Nuevo pago" to
 * the consultant (owner).
 */
final class PaymentMailer
{
    public const LINK = 'payment_link';
    public const RECEIVED = 'payment_received';
    public const NEW_FOR_CONSULTANT = 'payment_new';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $users,
        private readonly PlatformSettingsRepository $settings,
        private readonly PaymentLinks $links,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    public function paymentLink(Account $account, Enrollment $enrollment): void
    {
        $this->toContact($account, $enrollment->getContact(), self::LINK, sprintf('Tu plan con %s: %s', $account->getName(), $enrollment->getPlanName()), $this->plan($account, $enrollment) + [
            'payUrl' => $this->links->url($account, $enrollment),
        ]);
    }

    public function received(Account $account, Payment $payment): void
    {
        $context = $this->plan($account, $payment->getEnrollment()) + $this->payment($account, $payment);
        $this->toContact($account, $payment->getContact(), self::RECEIVED, sprintf('Recibimos tu pago: %s', $payment->getEnrollment()->getPlanName()), $context);

        $owner = $this->owner($account);
        if (null === $owner) {
            return;
        }
        $contact = $payment->getContact();
        $email = $this->email(self::NEW_FOR_CONSULTANT, $account, 'consultant', $context + [
            'contactName' => $contact->getFullName(),
            'contactEmail' => $contact->getEmail(),
            'contactUrl' => rtrim($this->appUrl, '/').'/admin/prospectos/'.$contact->getId().'?tab=planes',
        ])
            ->from(new Address($this->from, $this->settings->current()->getSenderName()))
            ->to(new Address($owner->getEmail(), $owner->getFullName()))
            ->subject(sprintf('Nuevo pago: %s · %s', $contact->getFullName(), $payment->getEnrollment()->getPlanName()));
        if (!$contact->isAnonymized()) {
            $email->replyTo(new Address($contact->getEmail(), $contact->getFullName()));
        }
        $this->mailer->send(EmailTag::apply($email, self::NEW_FOR_CONSULTANT, $account));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function toContact(Account $account, Contact $contact, string $kind, string $subject, array $context): void
    {
        if ($contact->isAnonymized()) {
            return;
        }
        $email = $this->email($kind, $account, 'contact', $context + ['contactName' => $contact->getFullName()])
            ->from(new Address($this->from, $account->getName()))
            ->to(new Address($contact->getEmail(), $contact->getFullName()))
            ->subject($subject);
        $owner = $this->owner($account);
        if (null !== $owner) {
            $email->replyTo(new Address($owner->getEmail(), $owner->getFullName()));
        }
        $this->mailer->send(EmailTag::apply($email, $kind, $account));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function email(string $kind, Account $account, string $audience, array $context): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->htmlTemplate('emails/payment.html.twig')
            ->textTemplate('emails/payment.txt.twig')
            ->context($context + [
                'locale' => $account->getLocale(),
                'sender' => 'contact' === $audience ? $account->getName() : null,
                'kind' => $kind,
                'audience' => $audience,
                'consultant' => $account->getName(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(Account $account, Enrollment $enrollment): array
    {
        return [
            'plan' => $enrollment->getPlanName(),
            'price' => MoneyText::format($account, $enrollment->getPrice(), $enrollment->getCurrency()),
            'sessions' => $enrollment->getSessionsIncluded(),
            'minutes' => $enrollment->getDurationMinutes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payment(Account $account, Payment $payment): array
    {
        return [
            'amount' => MoneyText::format($account, $payment->getAmount(), $payment->getCurrency()),
            'reference' => $payment->getReference(),
            'paidOn' => SessionTime::date($account, $payment->getPaidAt() ?? new \DateTimeImmutable()),
        ];
    }

    private function owner(Account $account): ?User
    {
        $owner = $this->users->findOwnersOf([$account])[(string) $account->getId()] ?? null;

        return null !== $owner && $owner->isActive() ? $owner : null;
    }
}
