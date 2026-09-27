<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UiTheme;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A person who signs in. Not scoped by the account filter (it must load during authentication, before the account
 * is known), so every listing of users filters by account explicitly.
 *
 * Invariants, enforced by the named constructors:
 *  - ROLE_SUPER_ADMIN: Pontiac's operator. No account.
 *  - ROLE_OWNER:       the consultant. One account.
 *  - ROLE_ASSISTANT:   helps a consultant run the practice. One account; no prices, payment keys, team or private notes.
 *  - ROLE_CLIENT:      someone who paid for a plan. One account; signs in at pontiac.co/<slug>/portal.
 *
 * Staff emails are unique across Pontiac (they sign in with email and password alone). A client's email is unique
 * within their consultant's account only: the same person can be a client of two consultants, with two logins.
 * $loginScope carries that rule into one unique index: "staff" for staff, the account id for a client.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email_scope', columns: ['email', 'login_scope'])]
#[ORM\Index(name: 'idx_user_account', columns: ['account_id'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use HasUuid;

    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    public const ROLE_OWNER = 'ROLE_OWNER';
    public const ROLE_ASSISTANT = 'ROLE_ASSISTANT';
    public const ROLE_CLIENT = 'ROLE_CLIENT';

    public const STAFF_SCOPE = 'staff';

    private const INVITATION_TTL = 'P7D';
    private const PASSWORD_RESET_TTL = 'PT1H';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(length: 36)]
    private string $loginScope;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $fullName;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles;

    // Null until the invited person sets a password.
    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Account $account;

    #[ORM\Column]
    private bool $active = true;

    // SHA-256 of the emailed token; the token itself is never stored.
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $invitationTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $invitationExpiresAt = null;

    // A client's contact: whose sessions, plans, notes and files the portal shows. Null for staff.
    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Contact $contact = null;

    // "¿Olvidaste tu contraseña?": SHA-256 of the emailed token, valid for an hour.
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $passwordResetTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetExpiresAt = null;

    // Light, dark or the device's; null until they choose, which reads as light (uiTheme()).
    #[ORM\Column(length: 10, nullable: true, enumType: UiTheme::class)]
    private ?UiTheme $uiTheme = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSignInAt = null;

    private function __construct(string $email, string $fullName, string $role, ?Account $account)
    {
        $this->id = Uuid::v7();
        $this->email = self::normalizeEmail($email);
        $this->fullName = $fullName;
        $this->roles = [$role];
        $this->account = $account;
        $this->loginScope = self::ROLE_CLIENT === $role ? self::clientScope($account ?? throw new \LogicException('A client belongs to an account.')) : self::STAFF_SCOPE;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function createSuperAdmin(string $email, string $fullName): self
    {
        return new self($email, $fullName, self::ROLE_SUPER_ADMIN, null);
    }

    public static function createOwner(Account $account, string $email, string $fullName): self
    {
        return new self($email, $fullName, self::ROLE_OWNER, $account);
    }

    public static function createAssistant(Account $account, string $email, string $fullName): self
    {
        return new self($email, $fullName, self::ROLE_ASSISTANT, $account);
    }

    public static function createClient(Account $account, string $email, string $fullName, ?Contact $contact = null): self
    {
        $client = new self($email, $fullName, self::ROLE_CLIENT, $account);
        $client->contact = $contact;

        return $client;
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** The login scope of an account's clients (see $loginScope). */
    public static function clientScope(Account $account): string
    {
        return $account->getId()->toRfc4122();
    }

    public static function hashInvitationToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return string the plain token to email (only its hash is kept)
     */
    public function issueInvitation(): string
    {
        if (null !== $this->password) {
            throw new \DomainException('User already has a password.');
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->invitationTokenHash = self::hashInvitationToken($token);
        $this->invitationExpiresAt = (new \DateTimeImmutable())->add(new \DateInterval(self::INVITATION_TTL));

        return $token;
    }

    public function hasPendingInvitation(\DateTimeImmutable $now): bool
    {
        return null !== $this->invitationTokenHash && null !== $this->invitationExpiresAt && $this->invitationExpiresAt > $now;
    }

    public function acceptInvitation(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
        $this->invitationTokenHash = null;
        $this->invitationExpiresAt = null;
    }

    /**
     * @return string the plain token to email (only its hash is kept); it replaces any earlier one
     */
    public function issuePasswordReset(\DateTimeImmutable $now = new \DateTimeImmutable()): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->passwordResetTokenHash = self::hashInvitationToken($token);
        $this->passwordResetExpiresAt = $now->add(new \DateInterval(self::PASSWORD_RESET_TTL));

        return $token;
    }

    public function hasValidPasswordReset(\DateTimeImmutable $now): bool
    {
        return null !== $this->passwordResetTokenHash && null !== $this->passwordResetExpiresAt && $this->passwordResetExpiresAt > $now;
    }

    /** A new password, from a reset link or from "Mi cuenta": any pending reset or invitation is spent. */
    public function changePassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
        $this->passwordResetTokenHash = null;
        $this->passwordResetExpiresAt = null;
        $this->invitationTokenHash = null;
        $this->invitationExpiresAt = null;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function linkContact(Contact $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * @return 'active'|'invited'|'none'
     */
    public function getLoginStatus(): string
    {
        return match (true) {
            null !== $this->password => 'active',
            null !== $this->invitationTokenHash => 'invited',
            default => 'none',
        };
    }

    public function getInvitationExpiresAt(): ?\DateTimeImmutable
    {
        return $this->invitationExpiresAt;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isStaff(): bool
    {
        return self::STAFF_SCOPE === $this->loginScope;
    }

    /**
     * The id, not the email: a client's email is not unique across Pontiac, while a JWT or an X-Switch-User header
     * must name one login. UserRepository::loadUserByIdentifier() still takes a staff email for the staff login.
     */
    public function getUserIdentifier(): string
    {
        return $this->id->toRfc4122();
    }

    public function getLastSignInAt(): ?\DateTimeImmutable
    {
        return $this->lastSignInAt;
    }

    public function markSignedIn(): static
    {
        $this->lastSignInAt = new \DateTimeImmutable();

        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->roles, true);
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): static
    {
        $this->password = $hashedPassword;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function uiTheme(): UiTheme
    {
        return $this->uiTheme ?? UiTheme::Light;
    }

    public function setUiTheme(UiTheme $uiTheme): static
    {
        $this->uiTheme = $uiTheme;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Whether a super admin may act as this user (ImpersonationVoter): an active owner or assistant of an active
     * account. Never a client (their data is theirs) and never another super admin.
     */
    public function canBeImpersonated(): bool
    {
        return ($this->hasRole(self::ROLE_OWNER) || $this->hasRole(self::ROLE_ASSISTANT))
            && $this->active
            && true === $this->account?->isActive();
    }
}
