<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Doctrine\AccountContext;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enters the signed-in user's account for each request, right after the firewall (priority 8) has authenticated
 * them. The account comes from the User loaded from the database — the impersonated one while a super admin acts as
 * someone — never from the token's claims. A public route enters the account of its URL's slug itself.
 */
final class AccountFilterSubscriber implements EventSubscriberInterface
{
    public const PLATFORM_FIREWALL = 'platform';

    public function __construct(
        private readonly Security $security,
        private readonly AccountContext $context,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->context->reset();

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Cross-account access requires both the role and the dedicated firewall.
        if ($user->hasRole(User::ROLE_SUPER_ADMIN)) {
            if (self::PLATFORM_FIREWALL === $this->security->getFirewallConfig($event->getRequest())?->getName()) {
                $this->context->enterPlatformScope();
            }

            return;
        }

        if (null !== $account = $user->getAccount()) {
            $this->context->enterAccount($account);
        }
    }
}
