<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Mail\EmailTag;
use App\Repository\AccountRepository;
use App\Repository\PlatformSettingsRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * "¿Olvidaste tu contraseña?": an emailed link, valid one hour, single use. Asking never says whether the email has
 * an account; only people who can sign in (active, with a password) get a link. Staff ask at /login, clients at their
 * consultant's portal.
 */
final class PasswordReset
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly AccountRepository $accounts,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly PlatformSettingsRepository $settings,
        #[Autowire(service: 'mailer.transports')]
        private readonly TransportInterface $transport,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    /** @param string|null $accountSlug the consultant whose portal asks; null for staff */
    public function request(string $email, ?string $accountSlug): void
    {
        $user = null === $accountSlug ? $this->users->loadUserByIdentifier($email) : $this->client($email, $accountSlug);
        if (!$user instanceof User || !$user->isActive() || 'active' !== $user->getLoginStatus()) {
            return;
        }
        if (null === $accountSlug && !$user->isStaff()) {
            return;
        }
        $token = $user->issuePasswordReset();
        $this->em->flush();
        $this->send($user, $token);
    }

    public function findValid(string $token): ?User
    {
        $user = $this->users->findOneByPasswordResetToken($token);

        return null !== $user && $user->isActive() && $user->hasValidPasswordReset(new \DateTimeImmutable()) ? $user : null;
    }

    /**
     * @return string where they sign in now
     */
    public function reset(User $user, string $plainPassword): string
    {
        $user->changePassword($this->hasher->hashPassword($user, $plainPassword));
        $this->em->flush();

        return self::loginPath($user);
    }

    public static function loginPath(User $user): string
    {
        $account = $user->getAccount();

        return $user->hasRole(User::ROLE_CLIENT) && null !== $account ? '/'.$account->getSlug().'/portal/ingresar' : '/login';
    }

    private function client(string $email, string $slug): ?User
    {
        $account = $this->accounts->findOneBySlug($slug);

        return null === $account || !$account->isActive() ? null : $this->users->findClient($account, $email);
    }

    private function send(User $user, string $token): void
    {
        $account = $user->getAccount();
        $settings = $this->settings->current();
        $sender = null !== $account && $user->hasRole(User::ROLE_CLIENT) ? $account->getName() : $settings->getSenderName();
        $email = (new TemplatedEmail())
            ->from(new Address($this->from, $sender))
            ->replyTo($settings->getSupportEmail())
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Cambia tu contraseña')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->textTemplate('emails/password_reset.txt.twig')
            ->context([
                'locale' => $account?->getLocale() ?? 'es',
                'sender' => $sender,
                'fullName' => $user->getFullName(),
                'resetUrl' => rtrim($this->appUrl, '/').'/restablecer?token='.rawurlencode($token),
            ]);

        $this->transport->send(EmailTag::apply($email, EmailTag::PASSWORD_RESET, $account));
    }
}
