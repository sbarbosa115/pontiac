<?php

declare(strict_types=1);

namespace App\Portal;

use App\Api\ApiException;
use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\User;
use App\Enum\AccountFeature;
use App\Repository\UserRepository;
use App\Security\InvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Who of a consultant's contacts can sign in to the portal: invited by hand from their page, or automatically when
 * they pay their first plan. One client login per contact.
 */
final class PortalAccess
{
    public const NONE = 'none';
    public const INVITED = 'invited';
    public const ACTIVE = 'active';
    public const DISABLED = 'disabled';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly InvitationService $invitations,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return self::NONE|self::INVITED|self::ACTIVE|self::DISABLED
     */
    public function status(?User $login): string
    {
        return match (true) {
            null === $login => self::NONE,
            !$login->isActive() => self::DISABLED,
            'active' === $login->getLoginStatus() => self::ACTIVE,
            default => self::INVITED,
        };
    }

    /** "Invitar al portal" (or "Reenviar invitación"): a new link by email. */
    public function invite(Account $account, Contact $contact): User
    {
        if (!$account->hasFeature(AccountFeature::Portal)) {
            throw ApiException::forbidden('feature_disabled', 'The client portal is not enabled for this consultant.');
        }
        if ($contact->isAnonymized()) {
            throw ApiException::conflict('already_anonymized', 'This person\'s data was erased.');
        }
        $login = $this->users->findClientOf($contact) ?? $this->adopt($account, $contact);
        if (null === $login) {
            return $this->invitations->inviteClient($account, $contact);
        }

        return $this->invitations->resend($login);
    }

    /**
     * After their first paid plan: invited if they have no login yet. Never fails the payment it follows: a problem
     * sending is logged, and the consultant can invite them by hand.
     */
    public function inviteAfterPayment(Account $account, Contact $contact): void
    {
        if (!$account->hasFeature(AccountFeature::Portal) || $contact->isAnonymized() || null !== $this->users->findClientOf($contact)) {
            return;
        }
        try {
            $this->invite($account, $contact);
        } catch (\Throwable $e) {
            $this->logger->error('Could not invite contact {contact} to the portal after their payment: {error}', ['contact' => (string) $contact->getId(), 'error' => $e->getMessage()]);
        }
    }

    /** "Quitar acceso" / "Devolver acceso". */
    public function setEnabled(Contact $contact, bool $enabled): User
    {
        $login = $this->users->findClientOf($contact) ?? throw ApiException::conflict('no_portal_access', 'This person was never invited to the portal.');
        if ($enabled && $contact->isAnonymized()) {
            throw ApiException::conflict('already_anonymized', 'This person\'s data was erased.');
        }
        $login->setActive($enabled);
        $this->em->flush();

        return $login;
    }

    /** A client login made before logins knew their contact, with the same email: it becomes theirs. */
    private function adopt(Account $account, Contact $contact): ?User
    {
        $login = $this->users->findClient($account, $contact->getEmail());
        if (null !== $login && null === $login->getContact()) {
            $login->linkContact($contact);
            $this->em->flush();

            return $login;
        }
        if (null !== $login) {
            throw ApiException::conflict('email_in_use', 'Another person of yours already signs in with this email.');
        }

        return null;
    }
}
