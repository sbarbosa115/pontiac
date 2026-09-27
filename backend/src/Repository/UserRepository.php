<?php

declare(strict_types=1);

namespace App\Repository;

use App\Api\Page;
use App\Api\Pagination;
use App\Entity\Account;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Users are not account-owned (they load during authentication, before the account is known): every query that
 * lists them names the account itself.
 *
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface, PasswordUpgraderInterface
{
    use ListQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * A user id (what a JWT and X-Switch-User carry, see User::getUserIdentifier()) or a staff email (the staff
     * login). An email never finds a client: they sign in per consultant (findClient()).
     */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        if (Uuid::isValid($identifier)) {
            return $this->findOneById($identifier);
        }

        return $this->findOneBy(['email' => User::normalizeEmail($identifier), 'loginScope' => User::STAFF_SCOPE]);
    }

    public function findOneById(string $id): ?User
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->createQueryBuilder('u')
            ->andWhere('u.id = :id')
            ->setParameter('id', Uuid::fromString($id), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * A client's login at one consultant, by email.
     */
    public function findClient(Account $account, string $email): ?User
    {
        return $this->findOneBy(['email' => User::normalizeEmail($email), 'loginScope' => User::clientScope($account)]);
    }

    public function findOneByInvitationToken(string $token): ?User
    {
        return $this->findOneBy(['invitationTokenHash' => User::hashInvitationToken($token)]);
    }

    /**
     * The account's team (its owner and assistants), searched by name and email; the owner first, then by name.
     */
    public function searchTeam(Account $account, string $term, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('IDENTITY(u.account) = :account')
            ->andWhere('u.loginScope = :staff')
            ->setParameter('account', $account->getId(), UuidType::NAME)
            ->setParameter('staff', User::STAFF_SCOPE)
            // Roles live in a JSON column; the owner's is the only one naming ROLE_OWNER.
            ->addSelect("CASE WHEN u.roles LIKE '%ROLE_OWNER%' THEN 0 ELSE 1 END AS HIDDEN ownerFirst")
            ->addOrderBy('ownerFirst', 'ASC')
            ->addOrderBy('u.fullName', 'ASC')
            ->addOrderBy('u.email', 'ASC');
        self::whereTerm($qb, $term, ['u.fullName', 'u.email']);

        return self::paginate($qb, $pagination);
    }

    /**
     * One member of the account's team; null for a client, another account's user, or an unknown id.
     */
    public function findTeamMember(Account $account, string $id): ?User
    {
        $user = $this->findOneById($id);

        return null !== $user && $user->isStaff() && true === $user->getAccount()?->getId()->equals($account->getId()) ? $user : null;
    }

    /**
     * Everyone a super admin may act as (User::canBeImpersonated()), by account name, owner first, then by name.
     *
     * @return list<User>
     */
    public function findImpersonatable(): array
    {
        $users = $this->createQueryBuilder('u')
            ->innerJoin('u.account', 'a')
            ->addSelect('a')
            ->andWhere('u.active = true')
            ->andWhere('a.active = true')
            ->andWhere('u.loginScope = :staff')
            ->setParameter('staff', User::STAFF_SCOPE)
            ->getQuery()
            ->getResult();

        $users = array_values(array_filter($users, static fn (User $user) => $user->canBeImpersonated()));
        usort($users, static fn (User $a, User $b) => [
            mb_strtolower((string) $a->getAccount()?->getName()), $a->hasRole(User::ROLE_OWNER) ? 0 : 1, mb_strtolower($a->getFullName()),
        ] <=> [
            mb_strtolower((string) $b->getAccount()?->getName()), $b->hasRole(User::ROLE_OWNER) ? 0 : 1, mb_strtolower($b->getFullName()),
        ]);

        return $users;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /**
     * The owners of these accounts, keyed by account id: one query for a whole page of consultants.
     *
     * @param list<Account> $accounts
     *
     * @return array<string, User>
     */
    public function findOwnersOf(array $accounts): array
    {
        $owners = [];
        foreach ($this->staffOf($accounts) as $user) {
            if ($user->hasRole(User::ROLE_OWNER)) {
                $owners[(string) $user->getAccount()?->getId()] = $user;
            }
        }

        return $owners;
    }

    /**
     * How many assistants and clients each of these accounts has, keyed by account id.
     *
     * @param list<Account> $accounts
     *
     * @return array<string, array{assistants: int, clients: int}>
     */
    public function countPeopleOf(array $accounts): array
    {
        if ([] === $accounts) {
            return [];
        }

        $rows = $this->createQueryBuilder('u')
            ->select('IDENTITY(u.account) AS account, u.loginScope AS scope, u.roles AS roles')
            ->andWhere('u.account IN (:accounts)')
            ->setParameter('accounts', array_map(static fn (Account $a) => $a->getId()->toBinary(), $accounts), ArrayParameterType::BINARY)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($accounts as $account) {
            $counts[(string) $account->getId()] = ['assistants' => 0, 'clients' => 0];
        }
        foreach ($rows as $row) {
            $id = Uuid::fromBinary((string) $row['account'])->toRfc4122();
            if (User::STAFF_SCOPE !== $row['scope']) {
                ++$counts[$id]['clients'];
            } elseif (\in_array(User::ROLE_ASSISTANT, (array) $row['roles'], true)) {
                ++$counts[$id]['assistants'];
            }
        }

        return $counts;
    }

    /**
     * How many of the account's assistants are active: what the assistant limit counts.
     */
    public function countActiveAssistants(Account $account): int
    {
        return \count(array_filter(
            $this->staffOf([$account]),
            static fn (User $user) => $user->isActive() && $user->hasRole(User::ROLE_ASSISTANT),
        ));
    }

    /**
     * Pontiac's own administrators, by name; ?q= searches name and email.
     */
    public function searchSuperAdmins(string $term, Pagination $pagination): Page
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.account IS NULL')
            ->andWhere("u.roles LIKE '%ROLE_SUPER_ADMIN%'")
            ->orderBy('u.fullName', 'ASC')
            ->addOrderBy('u.email', 'ASC');
        self::whereTerm($qb, $term, ['u.fullName', 'u.email']);

        return self::paginate($qb, $pagination);
    }

    public function findSuperAdmin(string $id): ?User
    {
        $user = $this->findOneById($id);

        return null !== $user && $user->hasRole(User::ROLE_SUPER_ADMIN) ? $user : null;
    }

    /**
     * @param list<Account> $accounts
     *
     * @return list<User>
     */
    private function staffOf(array $accounts): array
    {
        if ([] === $accounts) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->andWhere('u.account IN (:accounts)')
            ->andWhere('u.loginScope = :staff')
            ->setParameter('accounts', array_map(static fn (Account $a) => $a->getId()->toBinary(), $accounts), ArrayParameterType::BINARY)
            ->setParameter('staff', User::STAFF_SCOPE)
            ->getQuery()
            ->getResult();
    }
}
