<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Api\ApiException;
use App\Entity\User;
use App\Security\RequiresFeature;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enforces #[RequiresFeature]: an account without the feature gets a 403 feature_disabled from every endpoint that
 * carries it. The account is the one of the user the request runs as, loaded from the database, never from the token.
 */
final class FeatureGateSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => 'onControllerArguments'];
    }

    public function onControllerArguments(ControllerArgumentsEvent $event): void
    {
        $required = $event->getAttributes(RequiresFeature::class);
        if ([] === $required) {
            return;
        }

        $user = $this->security->getUser();
        $account = $user instanceof User ? $user->getAccount() : null;
        foreach ($required as $attribute) {
            if (null === $account || !$account->hasFeature($attribute->feature)) {
                throw ApiException::forbidden('feature_disabled', sprintf('The "%s" feature is not enabled for this consultant.', $attribute->feature->value));
            }
        }
    }
}
