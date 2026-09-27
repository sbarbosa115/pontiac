<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-super-admin',
    description: 'Creates a platform operator account (ROLE_SUPER_ADMIN).',
)]
final class CreateSuperAdminCommand extends Command
{
    private const MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Display name', 'Pontiac Admin')
            ->setHelp(<<<'HELP'
                Prompts for the password. In non-interactive mode (CI, provisioning scripts)
                the password is read from the SUPER_ADMIN_PASSWORD environment variable, so it
                never ends up in shell history.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = User::createSuperAdmin($input->getArgument('email'), $input->getOption('name'));

        $errors = $this->validator->validate($user);
        if (\count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error(sprintf('%s: %s', $error->getPropertyPath(), $error->getMessage()));
            }

            return Command::FAILURE;
        }

        $password = $input->isInteractive() ? $this->askPassword($io) : (getenv('SUPER_ADMIN_PASSWORD') ?: null);
        if (null === $password || mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $io->error(sprintf('A password of at least %d characters is required.', self::MIN_PASSWORD_LENGTH));

            return Command::FAILURE;
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Super admin %s created.', $user->getEmail()));

        return Command::SUCCESS;
    }

    private function askPassword(SymfonyStyle $io): ?string
    {
        $password = $io->askHidden(sprintf('Password (min %d characters)', self::MIN_PASSWORD_LENGTH));
        if ($password !== $io->askHidden('Repeat password')) {
            $io->error('Passwords do not match.');

            return null;
        }

        return $password;
    }
}
