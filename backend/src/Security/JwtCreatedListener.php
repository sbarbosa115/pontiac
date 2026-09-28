<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds what the frontend needs for routing (a client's portal lives under their consultant's slug). The backend
 * never trusts these claims for scoping; it always uses the User loaded from the database.
 */
#[AsEventListener(event: Events::JWT_CREATED)]
final class JwtCreatedListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload['userId'] = $user->getId()->toRfc4122();
        $payload['accountId'] = $user->getAccount()?->getId()->toRfc4122();
        $payload['accountSlug'] = $user->getAccount()?->getSlug();
        $event->setData($payload);
    }
}
