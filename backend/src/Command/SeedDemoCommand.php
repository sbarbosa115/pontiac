<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Account;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Demo data for a local stack: one consultant with an assistant and a client, and a super admin — one login per role,
 * all with the same password. Safe to run again: what exists is left as it is.
 */
#[AsCommand(name: 'app:seed-demo', description: 'Creates the demo consultant and one login per role (idempotent).')]
final class SeedDemoCommand extends Command
{
    public const PASSWORD = 'demo-password-123';
    public const SLUG = 'finanzas-claras';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository $accounts,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $account = $this->accounts->findOneBySlug(self::SLUG);
        if (null === $account) {
            $account = new Account('Finanzas Claras', self::SLUG);
            $this->em->persist($account);
        }

        $logins = [
            ['admin@pontiac.test', static fn () => User::createSuperAdmin('admin@pontiac.test', 'Paula Plataforma')],
            ['asesor@pontiac.test', static fn () => User::createOwner($account, 'asesor@pontiac.test', 'Andrés Asesor')],
            ['asistente@pontiac.test', static fn () => User::createAssistant($account, 'asistente@pontiac.test', 'Sofía Asistente')],
        ];
        foreach ($logins as [$email, $create]) {
            if (null === $this->users->loadUserByIdentifier($email)) {
                $this->withPassword($create());
            }
        }
        if (null === $this->users->findClient($account, 'cliente@pontiac.test')) {
            $this->withPassword(User::createClient($account, 'cliente@pontiac.test', 'Carlos Cliente'));
        }

        $this->em->flush();

        $io->success('Demo data ready.');
        $io->table(['Role', 'Sign in at', 'Email', 'Password'], [
            ['Super admin', '/login', 'admin@pontiac.test', self::PASSWORD],
            ['Consultant (owner)', '/login', 'asesor@pontiac.test', self::PASSWORD],
            ['Assistant', '/login', 'asistente@pontiac.test', self::PASSWORD],
            ['Client', '/'.self::SLUG.'/portal', 'cliente@pontiac.test', self::PASSWORD],
        ]);

        return Command::SUCCESS;
    }

    private function withPassword(User $user): void
    {
        $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
        $this->em->persist($user);
    }
}
