<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The invitation email. Sent at once, not through the queue: the person who invited is waiting on screen, and a
 * failure is reported to them (502 email_failed) so they can send it again.
 */
final class InvitationMailer
{
    public function __construct(
        #[Autowire(service: 'mailer.transports')]
        private readonly TransportInterface $transport,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    public function send(User $user, string $token): void
    {
        $account = $user->getAccount();
        // The account's locale (e.g. es_CO) falls back to its language, then to Spanish.
        $locale = $account?->getLocale() ?? 'es';
        // A consultant's team and clients are invited by the consultant; a consultant and super admins by Pontiac.
        $inviter = null !== $account && !$user->hasRole(User::ROLE_OWNER) ? $account->getName() : 'Pontiac';

        $email = (new TemplatedEmail())
            ->from(new Address($this->from, $inviter))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject($this->translator->trans('invitation.subject', ['inviter' => $inviter], 'emails', $locale))
            ->htmlTemplate('emails/invitation.html.twig')
            ->textTemplate('emails/invitation.txt.twig')
            ->context([
                'locale' => $locale,
                'fullName' => $user->getFullName(),
                'inviter' => $inviter,
                'role' => match (true) {
                    $user->hasRole(User::ROLE_CLIENT) => 'client',
                    $user->hasRole(User::ROLE_ASSISTANT) => 'assistant',
                    $user->hasRole(User::ROLE_OWNER) => 'owner',
                    default => 'super_admin',
                },
                'acceptUrl' => rtrim($this->appUrl, '/').'/invitacion?token='.rawurlencode($token),
            ]);

        $this->transport->send($email);
    }
}
