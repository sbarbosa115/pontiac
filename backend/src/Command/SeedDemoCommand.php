<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\AccountContext;
use App\Entity\Account;
use App\Entity\LandingPage;
use App\Entity\LeadCategory;
use App\Entity\BookingSession;
use App\Entity\Contact;
use App\Entity\ContactFlowState;
use App\Entity\EmailTemplate;
use App\Entity\FlowEvent;
use App\Flow\FlowGraph;
use App\Repository\FlowRepository;
use App\Entity\Enrollment;
use App\Entity\Payment;
use App\Entity\Plan;
use App\Entity\SessionNote;
use App\Enum\NoteVisibility;
use App\Enum\SessionStatus;
use App\Entity\User;
use App\Enum\PageTemplate;
use App\Page\TemplateCatalog;
use App\Repository\AccountRepository;
use App\Repository\LandingPageRepository;
use App\Repository\PlanRepository;
use App\Repository\WompiSettingsRepository;
use App\Payment\WompiKeys;
use App\Api\Input\WompiSettingsInput;
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
    // Wompi test keys that are not real: enough for the checkout signature and to simulate Wompi's events locally
    // (app:wompi:simulate-event, the e2e tests). Checkout at Wompi itself needs the consultant's real sandbox keys.
    public const WOMPI_PUBLIC_KEY = 'pub_test_pontiacdemo';
    public const WOMPI_EVENTS_SECRET = 'test_events_pontiacdemo';
    public const WOMPI_INTEGRITY_SECRET = 'test_integrity_pontiacdemo';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository $accounts,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AccountContext $context,
        private readonly LandingPageRepository $pages,
        private readonly PlanRepository $plans,
        private readonly WompiSettingsRepository $wompi,
        private readonly WompiKeys $wompiKeys,
        private readonly FlowRepository $flows,
        private readonly FlowGraph $graph,
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
        $this->seedPlans($account);
        $this->seedPayments($account);
        $this->seedClient($account);
        $this->seedFlow($account);

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

    /**
     * A free diagnostic people book from the home page, and a paid plan of two sessions.
     */
    private function seedPlans(Account $account): void
    {
        $this->context->enterAccount($account);
        if ([] !== $this->plans->findAllForPickers()) {
            return;
        }

        $free = new Plan($account, 'Diagnóstico gratuito', 'Una sesión de 45 minutos para ver dónde estás y qué hacer primero.', '0', 1, 45);
        $this->em->persist($free);
        $this->em->persist(new Plan($account, 'Plan A', 'Dos sesiones para armar tu presupuesto y un plan para tus deudas.', '250000.00', 2, 60));

        $home = $this->pages->findHome();
        if (null !== $home) {
            $template = TemplateCatalog::newContent($home->getTemplate(), $home->getTitle());
            $content = $home->getDraft();
            $ids = array_column($content['sections'], 'id');
            foreach ($template['sections'] as $i => $section) {
                if ('booking' === $section['type'] && !\in_array($section['id'], $ids, true)) {
                    array_splice($content['sections'], $i, 0, [$section]);
                }
            }
            foreach ($content['sections'] as &$section) {
                if ('booking' === $section['type']) {
                    $section['enabled'] = true;
                    $section['fields']['planId'] = (string) $free->getId();
                }
            }
            unset($section);
            $home->saveDraft($content)->publish();
        }
        $this->em->flush();
    }

    /**
     * Demo Wompi keys, and the plan page selling Plan A in its "Precios y pago" section, published.
     */
    private function seedPayments(Account $account): void
    {
        $this->context->enterAccount($account);
        if (null !== $this->wompi->current()) {
            return;
        }
        $keys = new WompiSettingsInput();
        $keys->publicKey = self::WOMPI_PUBLIC_KEY;
        $keys->eventsSecret = self::WOMPI_EVENTS_SECRET;
        $keys->integritySecret = self::WOMPI_INTEGRITY_SECRET;
        $this->wompiKeys->apply($this->wompi->forAccount($account), $keys);

        $paid = array_values(array_filter($this->plans->findAllForPickers(), static fn (Plan $plan) => !$plan->isFree()));
        $page = $this->pages->findOneBySlug('plan-2-sesiones');
        if (null !== $page && [] !== $paid) {
            $template = TemplateCatalog::newContent($page->getTemplate(), $page->getTitle());
            $content = $page->getDraft();
            $ids = array_column($content['sections'], 'id');
            foreach ($template['sections'] as $i => $section) {
                if ('payment' === $section['type'] && !\in_array($section['id'], $ids, true)) {
                    array_splice($content['sections'], $i, 0, [$section]);
                }
            }
            foreach ($content['sections'] as &$section) {
                if ('payment' === $section['type']) {
                    $section['enabled'] = true;
                    $section['fields']['planIds'] = [(string) $paid[0]->getId()];
                }
            }
            unset($section);
            $page->saveDraft($content)->publish();
        }
        $this->em->flush();
    }

    /**
     * The demo client's side: Carlos paid Plan A (by transfer); his first session was last week, with a shared and a
     * private note; the second is booked for next week.
     */
    private function seedClient(Account $account): void
    {
        $this->context->enterAccount($account);
        $login = $this->users->findClient($account, 'cliente@pontiac.test');
        $owner = $this->users->loadUserByIdentifier('asesor@pontiac.test');
        $plan = array_values(array_filter($this->plans->findAllForPickers(), static fn (Plan $p) => !$p->isFree()))[0] ?? null;
        if (null === $login || null !== $login->getContact() || !$owner instanceof User || null === $plan) {
            return;
        }

        $contact = (new Contact($account, 'Carlos Cliente', 'cliente@pontiac.test', '310 555 0101', null, 'demo'))->becomeClient();
        $enrollment = new Enrollment($contact, $plan, null);
        $this->em->persist($contact);
        $this->em->persist($enrollment);
        $this->em->persist(Payment::manual($enrollment, 'transfer', 'Demo', $owner, new \DateTimeImmutable('-10 days')));
        $enrollment->activate();

        $zone = new \DateTimeZone($account->getTimezone());
        [$past] = BookingSession::book($enrollment, (new \DateTimeImmutable('monday last week', $zone))->setTime(10, 0), 'https://meet.google.com/demo-pontiac', BookingSession::BOOKED_BY_STAFF);
        $past->close(SessionStatus::Done, new \DateTimeImmutable());
        [$next] = BookingSession::book($enrollment, (new \DateTimeImmutable('tuesday next week', $zone))->setTime(10, 0), 'https://meet.google.com/demo-pontiac', BookingSession::BOOKED_BY_STAFF);
        $this->em->persist($past);
        $this->em->persist($next);
        $this->em->persist(new SessionNote($past, $owner, "Tarea para la próxima sesión:\n- Registrar todos los gastos de una semana.\n- Traer los extractos de las dos tarjetas.", NoteVisibility::Shared));
        $this->em->persist(new SessionNote($past, $owner, 'Deuda de tarjeta alta (28 % E.A.). Proponer bola de nieve.', NoteVisibility::Private));
        $login->linkContact($contact);
        $this->em->flush();
    }

    /**
     * The ready flow, fed by the home page, with its "Sesión agendada" email; Carlos is in Cliente, and Laura has
     * waited in Seguimiento past its alert (she shows on Inicio).
     */
    private function seedFlow(Account $account): void
    {
        $this->context->enterAccount($account);
        if ([] !== $this->flows->findAllWithStages()) {
            return;
        }
        $template = new EmailTemplate($account, 'Sesión agendada', 'Nos vemos pronto, {nombre}', "Hola {nombre}:\n\nTu sesión con {asesor} quedó para el {fecha_sesion}. Trae tus extractos y una lista de tus gastos del mes.\n\nAquí puedes ver tus sesiones y archivos: {enlace_portal}");
        $this->em->persist($template);
        $flow = $this->graph->starter($account, 'Diagnóstico gratuito');
        $stages = [];
        foreach ($flow->getStages() as $stage) {
            $stages[$stage->getName()] = $stage;
        }
        $booked = $stages['Sesión agendada'];
        $booked->change($booked->getName(), $booked->getKind(), $booked->getPosition(), $booked->getX(), $booked->getY(), $template, 3);

        $home = $this->pages->findHome();
        if (null !== $home) {
            $content = $home->getDraft();
            $content['settings']['flowId'] = (string) $flow->getId();
            $home->saveDraft($content)->publish();
        }

        $owner = $this->users->loadUserByIdentifier('asesor@pontiac.test');
        $carlos = $this->users->findClient($account, 'cliente@pontiac.test')?->getContact();
        $laura = new Contact($account, 'Laura Gómez', 'laura@ejemplo.test', '300 123 4567', $home, 'demo');
        $this->em->persist($laura);
        foreach ([[$carlos, 'Cliente', '-10 days'], [$laura, 'Seguimiento', '-9 days']] as [$contact, $stageName, $since]) {
            if (null === $contact) {
                continue;
            }
            $at = new \DateTimeImmutable($since);
            $this->em->persist(new ContactFlowState($contact, $stages[$stageName], $at));
            $this->em->persist(new FlowEvent($contact, $flow, null, $stages['Nuevo'], FlowEvent::ADDED, $owner instanceof User ? $owner : null, $at->modify('-1 day')));
            $this->em->persist(new FlowEvent($contact, $flow, $stages['Nuevo'], $stages[$stageName], FlowEvent::MANUAL, $owner instanceof User ? $owner : null, $at));
        }
        $this->em->flush();
    }

    private function withPassword(User $user): void
    {
        $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
        $this->em->persist($user);
    }
}
