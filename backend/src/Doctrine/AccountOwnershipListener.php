<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\AccountOwnedInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Write-side counterpart of AccountScopeFilter (which only guards reads):
 *  - prePersist: new account-owned entities inherit the current account.
 *  - onFlush: rejects writes to another account's rows and relations that
 *    point at another account's entities.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::onFlush)]
final class AccountOwnershipListener
{
    public function __construct(private readonly AccountContext $context)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof AccountOwnedInterface && null === $entity->getAccount() && null !== $this->context->getAccount()) {
            $entity->setAccount($this->context->getAccount());
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $current = $this->context->getAccount();

        foreach ([...$uow->getScheduledEntityInsertions(), ...$uow->getScheduledEntityUpdates()] as $entity) {
            if (!$entity instanceof AccountOwnedInterface) {
                continue;
            }

            $owner = $entity->getAccount() ?? throw new AccountScopeViolation(sprintf('%s has no account and there is no account context.', $entity::class));

            if (null !== $current && !$owner->getId()->equals($current->getId())) {
                throw new AccountScopeViolation(sprintf('Refusing to write %s owned by another account.', $entity::class));
            }

            $metadata = $em->getClassMetadata($entity::class);
            foreach ($metadata->getAssociationNames() as $association) {
                if ('account' === $association) {
                    continue;
                }
                $value = $metadata->getFieldValue($entity, $association);
                $related = $metadata->isCollectionValuedAssociation($association) ? ($value ?? []) : [$value];

                foreach ($related as $item) {
                    if ($item instanceof AccountOwnedInterface && !$item->getAccount()?->getId()->equals($owner->getId())) {
                        throw new AccountScopeViolation(sprintf('%s::$%s points at an entity of another account.', $entity::class, $association));
                    }
                }
            }
        }
    }
}
