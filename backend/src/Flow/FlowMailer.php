<?php

declare(strict_types=1);

namespace App\Flow;

use App\Booking\SessionTime;
use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\EmailTemplate;
use App\Entity\FlowStage;
use App\Entity\User;
use App\Enum\EnrollmentStatus;
use App\Enum\SessionStatus;
use App\Mail\EmailTag;
use App\Payment\PaymentLinks;
use App\Repository\BookingSessionRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * A stage's email to the person who entered it, from the consultant: the template with its variables filled in and
 * the link to stop flow emails. Queued and tagged.
 */
final class FlowMailer
{
    public const KIND = 'flow_stage';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $users,
        private readonly BookingSessionRepository $sessions,
        private readonly EnrollmentRepository $enrollments,
        private readonly PaymentLinks $links,
        private readonly OptOutLink $optOut,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    /**
     * @return string|null the subject sent, or null when nothing was (no template, or they stopped flow emails)
     */
    public function stageEmail(Account $account, Contact $contact, FlowStage $stage): ?string
    {
        $template = $stage->getEmailTemplate();
        if (null === $template || !$template->isActive() || $contact->isAnonymized() || null !== $contact->getFlowEmailsStoppedAt()) {
            return null;
        }
        $values = $this->values($account, $contact);
        $subject = self::fill($template->getSubject(), $values);
        $body = self::fill($template->getBody(), $values);
        $optOut = $this->optOut->url($account, $contact);

        $email = (new TemplatedEmail())
            ->from(new Address($this->from, $account->getName()))
            ->to(new Address($contact->getEmail(), $contact->getFullName()))
            ->subject($subject)
            ->htmlTemplate('emails/flow_stage.html.twig')
            ->textTemplate('emails/flow_stage.txt.twig')
            ->context([
                'locale' => $account->getLocale(),
                'sender' => $account->getName(),
                'body' => $body,
                'paragraphs' => self::html($body),
                'optOutUrl' => $optOut,
            ]);
        $email->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$optOut.'>');
        $owner = $this->owner($account);
        if (null !== $owner) {
            $email->replyTo(new Address($owner->getEmail(), $owner->getFullName()));
        }
        $this->mailer->send(EmailTag::apply($email, self::KIND, $account));

        return $subject;
    }

    /**
     * A template's text with its variables filled in; unknown ones stay as written.
     *
     * @param array<string, string> $values
     */
    public static function fill(string $text, array $values): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', static fn (array $m) => \array_key_exists($m[1], $values) ? (string) $values[$m[1]] : $m[0], $text);
    }

    /**
     * The text as HTML paragraphs: escaped, line breaks kept, web addresses made links.
     *
     * @return list<string>
     */
    public static function html(string $text): array
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [];

        return array_map(static function (string $paragraph): string {
            $escaped = htmlspecialchars($paragraph, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            $linked = (string) preg_replace('~https?://[^\s<]+~', '<a href="$0">$0</a>', $escaped);

            return nl2br($linked, false);
        }, $paragraphs);
    }

    /**
     * @return array<string, string>
     */
    public function values(Account $account, Contact $contact): array
    {
        $base = rtrim($this->appUrl, '/').'/'.$account->getSlug();
        $next = null;
        $now = new \DateTimeImmutable();
        foreach ($this->sessions->findForContact($contact) as $session) {
            if (SessionStatus::Scheduled === $session->getStatus() && $session->getStartsAt() > $now && (null === $next || $session->getStartsAt() < $next->getStartsAt())) {
                $next = $session;
            }
        }
        $payUrl = '';
        foreach ($this->enrollments->findForContact($contact) as $enrollment) {
            if (EnrollmentStatus::PendingPayment === $enrollment->getStatus()) {
                $payUrl = $this->links->url($account, $enrollment) ?? '';
                break;
            }
        }

        // Nothing to name (no session booked, nothing to pay): words that still read in a sentence, and the page to
        // book from instead of a payment link.
        return [
            'nombre' => $contact->getFullName(),
            'asesor' => $account->getName(),
            'fecha_sesion' => null === $next ? 'día por definir' : SessionTime::dateTime($account, $next->getStartsAt()),
            'enlace_reserva' => $base.'#reserva',
            'enlace_pago' => '' === $payUrl ? $base.'#reserva' : $payUrl,
            'enlace_portal' => $base.'/portal',
        ];
    }

    private function owner(Account $account): ?User
    {
        $owner = $this->users->findOwnersOf([$account])[(string) $account->getId()] ?? null;

        return null !== $owner && $owner->isActive() ? $owner : null;
    }
}
