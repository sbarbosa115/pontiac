<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\AccountContext;
use App\Entity\Account;
use App\Entity\LandingPage;
use App\Entity\LeadCategory;
use App\Entity\User;
use App\Enum\PageTemplate;
use App\Page\TemplateCatalog;
use App\Repository\LandingPageRepository;
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
        private readonly AccountContext $context,
        private readonly LandingPageRepository $pages,
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
        $this->seedPages($account);

        $io->success('Demo data ready.');
        $io->table(['Role', 'Sign in at', 'Email', 'Password'], [
            ['Super admin', '/login', 'admin@pontiac.test', self::PASSWORD],
            ['Consultant (owner)', '/login', 'asesor@pontiac.test', self::PASSWORD],
            ['Assistant', '/login', 'asistente@pontiac.test', self::PASSWORD],
            ['Client', '/'.self::SLUG.'/portal', 'cliente@pontiac.test', self::PASSWORD],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Three lead categories, a published home page whose form sorts people into them, and a draft plan page.
     */
    private function seedPages(Account $account): void
    {
        // A console command reaches a consultant's data only once it enters their account.
        $this->context->enterAccount($account);
        if (null !== $this->pages->findHome()) {
            return;
        }

        $categories = [];
        foreach (['Deudas' => 'rose', 'Ahorro e inversión' => 'success', 'Pensión' => 'indigo'] as $name => $color) {
            $categories[$name] = new LeadCategory($account, $name, $color);
            $this->em->persist($categories[$name]);
        }

        $content = TemplateCatalog::newContent(PageTemplate::FreeDiagnostic, 'Diagnóstico financiero gratuito');
        $content['seo']['description'] = 'Una sesión gratuita de 45 minutos para ordenar tus finanzas y salir con un plan.';
        $content['form']['fields'] = [[
            'key' => 'preocupacion',
            'label' => '¿Qué te preocupa más de tus finanzas?',
            'type' => 'select',
            'required' => true,
            'options' => ['Mis deudas', 'Ahorrar e invertir', 'Mi pensión'],
            'optionCategories' => [
                'Mis deudas' => (string) $categories['Deudas']->getId(),
                'Ahorrar e invertir' => (string) $categories['Ahorro e inversión']->getId(),
                'Mi pensión' => (string) $categories['Pensión']->getId(),
            ],
        ]];
        $home = (new LandingPage($account, 'Diagnóstico gratuito', 'diagnostico', PageTemplate::FreeDiagnostic, $content))->setHome(true);
        $home->publish();
        $this->em->persist($home);

        $plan = new LandingPage($account, 'Plan de 2 sesiones', 'plan-2-sesiones', PageTemplate::PlanOffer, TemplateCatalog::newContent(PageTemplate::PlanOffer, 'Plan de finanzas personales'));
        $this->em->persist($plan);
        $this->em->flush();
    }

    private function withPassword(User $user): void
    {
        $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
        $this->em->persist($user);
    }
}
