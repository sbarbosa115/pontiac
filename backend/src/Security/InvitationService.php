<?php

declare(strict_types=1);

namespace App\Security;

use App\Api\ApiException;
use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Nobody chooses another person's password: an invited person gets an emailed link (valid 7 days, single use) and
 * sets their own. The same link works for staff and for clients; where they sign in afterwards differs.
 */
final class InvitationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly InvitationMailer $mailer,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function inviteOwner(Account $account, string $email, string $fullName): User
    {
        $this->assertStaffEmailAvailable($email);
        $user = User::createOwner($account, $email, $fullName);
        $this->em->persist($user);

        return $this->issue($user);
    }

    public function inviteAssistant(Account $account, string $email, string $fullName): User
    {
        $this->assertStaffEmailAvailable($email);
        $user = User::createAssistant($account, $email, $fullName);
        $this->em->persist($user);

        return $this->issue($user);
    }

    public function inviteSuperAdmin(string $email, string $fullName): User
    {
        $this->assertStaffEmailAvailable($email);
        $user = User::createSuperAdmin($email, $fullName);
        $this->em->persist($user);

        return $this->issue($user);
    }

    /** A contact's client login, with its invitation (the portal, at /<consultant>/portal). */
    public function inviteClient(Account $account, Contact $contact): User
    {
        $user = User::createClient($account, $contact->getEmail(), $contact->getFullName(), $contact);
        $this->em->persist($user);

        return $this->issue($user);
    }

    /**
     * Sends a new link to someone who has not set their password yet (the previous link stops working).
     */
    public function resend(User $user): User
    {
        if (!$user->isActive()) {
            throw ApiException::conflict('user_disabled', 'Enable this account before inviting them again.');
        }
        if ('active' === $user->getLoginStatus()) {
            throw ApiException::conflict('already_has_access', 'This person has already set up their account.');
        }

        return $this->issue($user);
    }

    public function findPending(string $token): ?User
    {
        $user = $this->users->findOneByInvitationToken($token);

        return null !== $user && $user->isActive() && $user->hasPendingInvitation(new \DateTimeImmutable()) ? $user : null;
    }

    public function accept(User $user, string $plainPassword): void
    {
        $user->acceptInvitation($this->hasher->hashPassword($user, $plainPassword));
        $this->em->flush();
    }

    public function assertStaffEmailAvailable(string $email): void
    {
        if (null !== $this->users->loadUserByIdentifier($email)) {
            throw ApiException::conflict('email_in_use', 'This email address already belongs to another account.');
        }
    }

    private function issue(User $user): User
    {
        $token = $user->issueInvitation();
        // Flush first: if sending fails, the invitation can simply be sent again.
        $this->em->flush();
        $this->mailer->send($user, $token);

        return $user;
    }
}
