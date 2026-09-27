<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\LandingPage;
use App\Entity\User;
use App\Repository\PlatformSettingsRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * The emails a new lead sets off, both queued (the visitor is not kept waiting for the mail server):
 *  - to the consultant: "Nuevo prospecto", with what the person wrote and a link to their page in the admin;
 *  - to the visitor, on a lead-magnet page: the resource they asked for, from the consultant.
 */
final class LeadMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $users,
        private readonly PlatformSettingsRepository $settings,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    /**
     * @param list<array{key: string, label: string, value: string}> $answers
     */
    public function newLead(Account $account, Contact $contact, LandingPage $page, array $answers): void
    {
        $owner = $this->users->findOwnersOf([$account])[(string) $account->getId()] ?? null;
        if (null === $owner || !$owner->isActive()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->from, $this->settings->current()->getSenderName()))
            ->to(new Address($owner->getEmail(), $owner->getFullName()))
            ->replyTo(new Address($contact->getEmail(), $contact->getFullName()))
            ->subject(sprintf('Nuevo prospecto: %s', $contact->getFullName()))
            ->htmlTemplate('emails/new_lead.html.twig')
            ->textTemplate('emails/new_lead.txt.twig')
            ->context([
                'locale' => $account->getLocale(),
                'contact' => ['name' => $contact->getFullName(), 'email' => $contact->getEmail(), 'phone' => $contact->getPhone()],
                'pageTitle' => $page->getTitle(),
                'answers' => $answers,
                'contactUrl' => rtrim($this->appUrl, '/').'/admin/prospectos/'.$contact->getId(),
            ]);

        $this->mailer->send(EmailTag::apply($email, EmailTag::NEW_LEAD, $account));
    }

    public function resource(Account $account, Contact $contact, LandingPage $page, string $resourceUrl): void
    {
        $owner = $this->users->findOwnersOf([$account])[(string) $account->getId()] ?? null;
        $email = (new TemplatedEmail())
            ->from(new Address($this->from, $account->getName()))
            ->to(new Address($contact->getEmail(), $contact->getFullName()))
            ->subject(sprintf('%s: %s', $account->getName(), $page->getTitle()))
            ->htmlTemplate('emails/resource.html.twig')
            ->textTemplate('emails/resource.txt.twig')
            ->context([
                'locale' => $account->getLocale(),
                'sender' => $account->getName(),
                'name' => $contact->getFullName(),
                'pageTitle' => $page->getTitle(),
                'resourceUrl' => $resourceUrl,
            ]);
        if ($owner instanceof User) {
            $email->replyTo(new Address($owner->getEmail(), $owner->getFullName()));
        }

        $this->mailer->send(EmailTag::apply($email, EmailTag::RESOURCE, $account));
    }
}
