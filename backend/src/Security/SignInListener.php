<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Records when a staff member last signed in with their password (the super admin sees it per consultant). Every
 * JWT request is a "login success" too, so only the password login counts; clients are recorded by
 * PortalLoginController.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
final class SignInListener
{
    public const PASSWORD_FIREWALL = 'login';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (self::PASSWORD_FIREWALL !== $event->getFirewallName() || !$user instanceof User) {
            return;
        }

        $user->markSignedIn();
        $this->em->flush();
    }
}
