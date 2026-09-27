<?php

declare(strict_types=1);

namespace App\Page;

use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\LandingPage;
use App\Entity\LeadSubmission;
use App\Enum\FlowTrigger;
use App\Flow\ContactMoment;
use App\Mail\LeadMailer;
use App\Repository\ContactRepository;
use App\Repository\LeadCategoryRepository;
use App\Repository\PlatformSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A visitor sent a page's form: check what they wrote, make them a prospecto (or add to the one they already are),
 * sort them into a category, and let the consultant know. Checks mirror the form's (templates/public/page/sections/
 * form.html.twig); messages are Spanish, for the visitor.
 */
final class LeadIntake
{
    private const EMAIL = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/';
    private const PHONE = '/^\+?[0-9][0-9 ().-]*[0-9)]$/';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContactRepository $contacts,
        private readonly LeadCategoryRepository $categories,
        private readonly PlatformSettingsRepository $settings,
        private readonly TimeToken $timeToken,
        private readonly LeadMailer $mailer,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    /**
     * Whether a person, not a script, sent it: the trap field empty and the form not sent back instantly. A script's
     * form is answered as if it went through, so it learns nothing, and nothing is saved.
     *
     * @param array<mixed> $data
     */
    public function isHuman(array $data): bool
    {
        return '' === trim((string) ($data['website'] ?? '')) && $this->timeToken->isHuman((string) ($data['_t'] ?? ''));
    }

    /**
     * @param array<string, mixed> $content the published content the visitor saw
     * @param array<mixed>         $data    what the form sent
     *
     * @return array{values: array<string, string>, errors: array<string, string>}
     */
    public function check(array $content, array $data): array
    {
        ['values' => $values, 'errors' => $errors] = $this->checkPerson($data);
        foreach ($content['form']['fields'] as $field) {
            $value = $values[$field['key']] ?? '';
            if ($field['required'] && '' === $value) {
                $errors[$field['key']] = 'checkbox' === $field['type'] ? 'Marca esta casilla para continuar.' : 'Completa este campo.';
            } elseif ('select' === $field['type'] && '' !== $value && !\in_array($value, $field['options'], true)) {
                $errors[$field['key']] = 'Elige una de las opciones.';
            } elseif ('number' === $field['type'] && '' !== $value && !is_numeric($value)) {
                $errors[$field['key']] = 'Escribe un número.';
            }
        }
        // Consent is asked last on the form: its message comes last too.
        if (isset($errors['consent'])) {
            $consent = $errors['consent'];
            unset($errors['consent']);
            $errors['consent'] = $consent;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * What every form asks of a person, checked: name, email, phone (optional) and consent. Shared by the lead form
     * and the booking form.
     *
     * @param array<mixed> $data
     *
     * @return array{values: array<string, string>, errors: array<string, string>}
     */
    public function checkPerson(array $data): array
    {
        $values = [];
        foreach ($data as $key => $value) {
            if (\is_string($key) && \is_scalar($value)) {
                $values[$key] = mb_substr(trim((string) $value), 0, 2000);
            }
        }

        $errors = [];
        $name = $values['name'] ?? '';
        if ('' === $name) {
            $errors['name'] = 'Escribe tu nombre.';
        } elseif (mb_strlen($name) > 180) {
            $errors['name'] = 'Tu nombre es demasiado largo.';
        }
        $email = $values['email'] ?? '';
        if (1 !== preg_match(self::EMAIL, $email) || mb_strlen($email) > 180) {
            $errors['email'] = 'Escribe un correo válido, por ejemplo nombre@correo.com.';
        }
        $phone = $values['phone'] ?? '';
        $digits = \strlen((string) preg_replace('/\D/', '', $phone));
        if ('' !== $phone && (1 !== preg_match(self::PHONE, $phone) || $digits < 7 || $digits > 15)) {
            $errors['phone'] = 'Escribe un teléfono de 7 a 15 dígitos.';
        }
        if ('1' !== ($values['consent'] ?? '')) {
            $errors['consent'] = 'Para enviar tus datos debes aceptar la política de privacidad.';
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * The contact these checked details belong to: the one with this email at this consultant (updated with what they
     * wrote now), or a new prospecto. Persisted, not flushed.
     *
     * @param array<string, string> $values checked by checkPerson()
     */
    public function person(Account $account, ?LandingPage $page, array $values): Contact
    {
        $policyHash = hash('sha256', '' !== $account->getPrivacyText() ? $account->getPrivacyText() : $this->settings->current()->getDefaultPrivacyText());
        $phone = '' === ($values['phone'] ?? '') ? null : $values['phone'];

        $contact = $this->contacts->findOneByEmail($values['email']);
        if (null === $contact) {
            $contact = new Contact($account, $values['name'], $values['email'], $phone, $page, $policyHash);
            $this->em->persist($contact);
        } else {
            $contact->answeredAgain($values['name'], $phone, $policyHash);
        }

        return $contact;
    }

    /**
     * Records a checked form. The account must have been entered (AccountContext) by the caller.
     *
     * @param array<string, mixed>  $content the published content the visitor saw
     * @param array<string, string> $values  checked by check()
     * @param array<string, string> $utm
     */
    public function record(Account $account, LandingPage $page, array $content, array $values, array $utm, ?string $referrer): Contact
    {
        $contact = $this->person($account, $page, $values);

        $answers = [];
        $categoryId = $content['settings']['defaultCategoryId'];
        foreach ($content['form']['fields'] as $field) {
            $value = $values[$field['key']] ?? '';
            if ('checkbox' === $field['type']) {
                $value = '' === $value ? 'No' : 'Sí';
            }
            $answers[] = ['key' => $field['key'], 'label' => $field['label'], 'value' => $value];
            // The answer to a select can say which category the person belongs to; it wins over the page's default.
            if ('select' === $field['type'] && isset($field['optionCategories'][$value])) {
                $categoryId = $field['optionCategories'][$value];
            }
        }
        if (null !== $categoryId) {
            $category = $this->categories->findOneById($categoryId);
            if (null !== $category) {
                $contact->setCategory($category);
            }
        }

        $this->em->persist(new LeadSubmission($contact, $page, $answers, $utm, $referrer));
        $this->em->flush();
        $this->events->dispatch(new ContactMoment($account, $contact, FlowTrigger::LeadSubmitted, $page));

        $this->mailer->newLead($account, $contact, $page, $answers);
        foreach ($content['sections'] as $section) {
            if ('form' === $section['type'] && '' !== ($section['fields']['resourceUrl'] ?? '')) {
                $this->mailer->resource($account, $contact, $page, $section['fields']['resourceUrl']);
            }
        }

        return $contact;
    }
}
