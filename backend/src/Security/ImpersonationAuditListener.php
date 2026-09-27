<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Leaves a trace of every request a super admin makes as a consultant or an assistant.
 * The api firewall is stateless, so this fires once per impersonated request.
 */
#[AsEventListener(event: SecurityEvents::SWITCH_USER)]
final class ImpersonationAuditListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(SwitchUserEvent $event): void
    {
        $token = $event->getToken();
        if (!$token instanceof SwitchUserToken) {
            return;
        }

        $request = $event->getRequest();
        $impersonator = $token->getOriginalToken()->getUser();
        $target = $event->getTargetUser();
        $this->logger->notice('Super admin request as another user.', [
            // Identifiers are user ids; the emails are for whoever reads the log.
            'impersonator' => $token->getOriginalToken()->getUserIdentifier(),
            'impersonatorEmail' => $impersonator instanceof User ? $impersonator->getEmail() : null,
            'user' => $target->getUserIdentifier(),
            'userFullName' => $target instanceof User ? $target->getFullName() : null,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
        ]);
    }
}
