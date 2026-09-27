<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Output\AccentOutput;
use App\Api\Output\ClientFileOutput;
use App\Api\Output\PortalAccessOutput;
use App\Api\Output\PortalNoteOutput;
use App\Api\Output\PortalPaymentOutput;
use App\Api\Output\PortalPlanOutput;
use App\Api\Output\PortalSessionOutput;
use App\Entity\ClientFile;
use App\Api\Output\EnrollmentOutput;
use App\Api\Output\PaymentOutput;
use App\Api\Output\SessionNoteOutput;
use App\Api\Output\WompiSettingsOutput;
use App\Entity\Enrollment;
use App\Entity\Payment;
use App\Entity\SessionNote;
use App\Entity\WompiSettings;
use App\Api\Output\AvailabilityExceptionOutput;
use App\Api\Output\AvailabilityOutput;
use App\Api\Output\ContactRefOutput;
use App\Api\Output\MoneyOutput;
use App\Api\Output\PlanOutput;
use App\Api\Output\SessionOutput;
use App\Api\Output\SlotDayOutput;
use App\Api\Output\SlotOutput;
use App\Api\Output\WeeklyRuleOutput;
use App\Booking\SessionTime;
use App\Entity\Availability;
use App\Entity\BookingSession;
use App\Entity\Plan;
use App\Api\Output\AnswerOutput;
use App\Api\Output\CategoryRefOutput;
use App\Api\Output\ContactDetailOutput;
use App\Api\Output\ContactSummaryOutput;
use App\Api\Output\FieldSpecOutput;
use App\Api\Output\ItemFieldSpecOutput;
use App\Api\Output\LeadCategoryOutput;
use App\Api\Output\MediaAssetOutput;
use App\Api\Output\PageCatalogOutput;
use App\Api\Output\PageDetailOutput;
use App\Api\Output\PageRefOutput;
use App\Api\Output\PageSummaryOutput;
use App\Api\Output\SectionTypeOutput;
use App\Api\Output\SubmissionOutput;
use App\Api\Output\TemplateOutput;
use App\Api\Output\TemplateSectionOutput;
use App\Api\Output\UtmOutput;
use App\Entity\Contact;
use App\Entity\LandingPage;
use App\Entity\LeadCategory;
use App\Entity\LeadSubmission;
use App\Entity\MediaAsset;
use App\Page\ContentValidator;
use App\Page\TemplateCatalog;
use App\Api\Output\AccountDetailOutput;
use App\Api\Output\AccountOwnerOutput;
use App\Api\Output\AccountRefOutput;
use App\Api\Output\AccountSummaryOutput;
use App\Api\Output\ImpersonatableAccountOutput;
use App\Api\Output\ImpersonatableUserOutput;
use App\Api\Output\MeAccountOutput;
use App\Api\Output\OutgoingEmailOutput;
use App\Api\Output\PersonOutput;
use App\Api\Output\PlatformSettingsOutput;
use App\Api\Output\SettingChangeOutput;
use App\Api\Output\SettingsChangeOutput;
use App\Api\Output\SuperAdminOutput;
use App\Api\Output\TeamMemberOutput;
use App\Entity\Account;
use App\Entity\OutgoingEmail;
use App\Entity\PlatformSettings;
use App\Entity\PlatformSettingsChange;
use App\Entity\User;
use App\Enum\AccountFeature;
use App\Enum\PageTemplate;

/**
 * Entities → Output DTOs, one place per shape, so every endpoint that shows the same thing shows it the same way.
 * A Presenter method reads only what the repository fetched (no N+1: ListQueryCountTest).
 */
final class Presenter
{
    public static function meAccount(Account $account): MeAccountOutput
    {
        return new MeAccountOutput(
            id: (string) $account->getId(),
            name: $account->getName(),
            slug: $account->getSlug(),
            country: $account->getCountry(),
            currency: $account->getCurrency(),
            locale: $account->getLocale(),
            timezone: $account->getTimezone(),
            features: self::featureValues($account->getFeatures()),
        );
    }

    /**
     * @param array{assistants: int, clients: int} $people
     */
    public static function accountSummary(Account $account, ?User $owner, array $people): AccountSummaryOutput
    {
        return new AccountSummaryOutput(
            id: (string) $account->getId(),
            name: $account->getName(),
            slug: $account->getSlug(),
            active: $account->isActive(),
            createdAt: (string) self::timestamp($account->getCreatedAt()),
            owner: null === $owner ? null : self::accountOwner($owner),
            assistants: $people['assistants'],
            clients: $people['clients'],
        );
    }

    /**
     * @param array{assistants: int, clients: int} $people
     */
    public static function accountDetail(Account $account, ?User $owner, array $people): AccountDetailOutput
    {
        return new AccountDetailOutput(
            id: (string) $account->getId(),
            name: $account->getName(),
            slug: $account->getSlug(),
            country: $account->getCountry(),
            currency: $account->getCurrency(),
            locale: $account->getLocale(),
            timezone: $account->getTimezone(),
            active: $account->isActive(),
            createdAt: (string) self::timestamp($account->getCreatedAt()),
            maxPublishedPages: $account->getMaxPublishedPages(),
            maxAssistants: $account->getMaxAssistants(),
            storageMb: $account->getStorageMb(),
            maxFileMb: $account->getMaxFileMb(),
            features: self::featureValues($account->getFeatures()),
            owner: null === $owner ? null : self::accountOwner($owner),
            assistants: $people['assistants'],
            clients: $people['clients'],
        );
    }

    public static function accountOwner(User $owner): AccountOwnerOutput
    {
        return new AccountOwnerOutput(
            id: (string) $owner->getId(),
            fullName: $owner->getFullName(),
            email: $owner->getEmail(),
            loginStatus: $owner->getLoginStatus(),
            lastSignInAt: self::timestamp($owner->getLastSignInAt()),
        );
    }

    public static function platformSettings(PlatformSettings $settings): PlatformSettingsOutput
    {
        return new PlatformSettingsOutput(
            platformName: $settings->getPlatformName(),
            supportEmail: $settings->getSupportEmail(),
            senderName: $settings->getSenderName(),
            enabledTemplates: array_map(static fn (PageTemplate $t) => $t->value, $settings->getEnabledTemplates()),
            reminderHours: $settings->getReminderHours(),
            minNoticeHours: $settings->getMinNoticeHours(),
            bookingWindowDays: $settings->getBookingWindowDays(),
            clientCancelHours: $settings->getClientCancelHours(),
            sessionBufferMinutes: $settings->getSessionBufferMinutes(),
            defaultMaxPublishedPages: $settings->getDefaultMaxPublishedPages(),
            defaultMaxAssistants: $settings->getDefaultMaxAssistants(),
            defaultStorageMb: $settings->getDefaultStorageMb(),
            defaultMaxFileMb: $settings->getDefaultMaxFileMb(),
            defaultFeatures: self::featureValues($settings->getDefaultFeatures()),
            termsText: $settings->getTermsText(),
            defaultPrivacyText: $settings->getDefaultPrivacyText(),
            reservedSlugs: $settings->getReservedSlugs(),
            updatedAt: self::timestamp($settings->getUpdatedAt()),
        );
    }

    public static function settingsChange(PlatformSettingsChange $change): SettingsChangeOutput
    {
        $by = $change->getChangedBy();
        $changes = [];
        foreach ($change->getChanges() as $field => ['from' => $from, 'to' => $to]) {
            $changes[] = new SettingChangeOutput(field: $field, from: self::settingText($from), to: self::settingText($to));
        }

        return new SettingsChangeOutput(
            id: (string) $change->getId(),
            changedAt: (string) self::timestamp($change->getChangedAt()),
            changedBy: new PersonOutput(id: (string) $by->getId(), fullName: $by->getFullName(), email: $by->getEmail()),
            changes: $changes,
        );
    }

    public static function outgoingEmail(OutgoingEmail $email): OutgoingEmailOutput
    {
        $account = $email->getAccount();

        return new OutgoingEmailOutput(
            id: (string) $email->getId(),
            kind: $email->getKind(),
            recipient: $email->getRecipient(),
            subject: $email->getSubject(),
            status: $email->getStatus()->value,
            error: $email->getError(),
            sentAt: (string) self::timestamp($email->getSentAt()),
            account: null === $account ? null : new AccountRefOutput(id: (string) $account->getId(), name: $account->getName()),
        );
    }

    public static function superAdmin(User $user, User $viewer): SuperAdminOutput
    {
        return new SuperAdminOutput(
            id: (string) $user->getId(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            active: $user->isActive(),
            loginStatus: $user->getLoginStatus(),
            lastSignInAt: self::timestamp($user->getLastSignInAt()),
            you: $user->getId()->equals($viewer->getId()),
        );
    }

    public static function pageRef(LandingPage $page): PageRefOutput
    {
        return new PageRefOutput(id: (string) $page->getId(), title: $page->getTitle(), slug: $page->getSlug());
    }

    /** Where visitors find the page. */
    public static function pagePath(LandingPage $page): string
    {
        $account = $page->getAccount() ?? throw new \LogicException('A page belongs to an account.');

        return '/'.$account->getSlug().($page->isHome() ? '' : '/'.$page->getSlug());
    }

    public static function pageSummary(LandingPage $page, int $leadsLast30Days): PageSummaryOutput
    {
        return new PageSummaryOutput(
            id: (string) $page->getId(),
            title: $page->getTitle(),
            slug: $page->getSlug(),
            path: self::pagePath($page),
            template: $page->getTemplate()->value,
            status: $page->getStatus()->value,
            home: $page->isHome(),
            hasUnpublishedChanges: $page->hasUnpublishedChanges(),
            leadsLast30Days: $leadsLast30Days,
            updatedAt: (string) self::timestamp($page->getUpdatedAt()),
            publishedAt: self::timestamp($page->getPublishedAt()),
        );
    }

    public static function pageDetail(LandingPage $page): PageDetailOutput
    {
        return new PageDetailOutput(
            id: (string) $page->getId(),
            title: $page->getTitle(),
            slug: $page->getSlug(),
            path: self::pagePath($page),
            template: $page->getTemplate()->value,
            status: $page->getStatus()->value,
            home: $page->isHome(),
            hasUnpublishedChanges: $page->hasUnpublishedChanges(),
            updatedAt: (string) self::timestamp($page->getUpdatedAt()),
            publishedAt: self::timestamp($page->getPublishedAt()),
            draft: $page->getDraft(),
        );
    }

    /**
     * @param list<PageTemplate> $enabled
     */
    public static function pageCatalog(array $enabled): PageCatalogOutput
    {
        $templates = array_map(static fn (PageTemplate $template) => new TemplateOutput(
            key: $template->value,
            enabled: \in_array($template, $enabled, true),
            sections: array_map(static fn (array $section) => new TemplateSectionOutput(id: $section['id'], type: $section['type']), TemplateCatalog::sections($template)),
        ), PageTemplate::cases());

        $types = [];
        foreach (TemplateCatalog::SECTION_TYPES as $type => $fields) {
            $specs = [];
            foreach ($fields as $name => $spec) {
                $specs[] = new FieldSpecOutput(name: $name, kind: $spec['kind'], required: $spec['required'] ?? false, max: $spec['max'] ?? null, maxItems: $spec['maxItems'] ?? null, fields: self::itemFields($spec['fields'] ?? []));
            }
            $types[] = new SectionTypeOutput(type: $type, fields: $specs);
        }

        $accents = [];
        foreach (TemplateCatalog::ACCENTS as $key => [$color]) {
            $accents[] = new AccentOutput(key: $key, color: $color);
        }

        return new PageCatalogOutput(templates: $templates, sectionTypes: $types, accents: $accents, fieldTypes: ContentValidator::FIELD_TYPES, maxExtraFields: ContentValidator::MAX_EXTRA_FIELDS);
    }

    public static function mediaAsset(MediaAsset $asset): MediaAssetOutput
    {
        $account = $asset->getAccount() ?? throw new \LogicException('An asset belongs to an account.');
        $base = '/'.$account->getSlug().'/media/'.$asset->getId().'-';

        return new MediaAssetOutput(
            id: (string) $asset->getId(),
            originalName: $asset->getOriginalName(),
            width: $asset->getWidth(),
            height: $asset->getHeight(),
            sizeBytes: $asset->getTotalBytes(),
            altText: $asset->getAltText(),
            active: $asset->isActive(),
            createdAt: (string) self::timestamp($asset->getCreatedAt()),
            thumbUrl: $base.$asset->variantFor(480).'.webp',
            url: $base.$asset->variantFor(1600).'.webp',
        );
    }

    public static function leadCategory(LeadCategory $category): LeadCategoryOutput
    {
        return new LeadCategoryOutput(id: (string) $category->getId(), name: $category->getName(), color: $category->getColor(), active: $category->isActive());
    }

    public static function categoryRef(?LeadCategory $category): ?CategoryRefOutput
    {
        return null === $category ? null : new CategoryRefOutput(id: (string) $category->getId(), name: $category->getName(), color: $category->getColor(), active: $category->isActive());
    }

    public static function contactSummary(Contact $contact): ContactSummaryOutput
    {
        return new ContactSummaryOutput(
            id: (string) $contact->getId(),
            fullName: $contact->getFullName(),
            email: $contact->getEmail(),
            phone: $contact->getPhone(),
            status: $contact->getStatus()->value,
            category: self::categoryRef($contact->getCategory()),
            sourcePage: null === $contact->getSourcePage() ? null : self::pageRef($contact->getSourcePage()),
            lastActivityAt: (string) self::timestamp($contact->getLastActivityAt()),
            anonymized: $contact->isAnonymized(),
        );
    }

    public static function plan(Plan $plan): PlanOutput
    {
        return new PlanOutput(
            id: (string) $plan->getId(),
            name: $plan->getName(),
            description: $plan->getDescription(),
            price: new MoneyOutput(amount: $plan->getPrice(), currency: $plan->getCurrency()),
            free: $plan->isFree(),
            sessions: $plan->getSessions(),
            durationMinutes: $plan->getDurationMinutes(),
            active: $plan->isActive(),
        );
    }

    public static function availability(Availability $availability, Account $account): AvailabilityOutput
    {
        return new AvailabilityOutput(
            weeklyRules: array_map(static fn (array $r) => new WeeklyRuleOutput(weekday: $r['weekday'], from: $r['from'], to: $r['to']), $availability->getWeeklyRules()),
            exceptions: array_map(static fn (array $e) => new AvailabilityExceptionOutput(date: $e['date'], from: $e['from'], to: $e['to']), $availability->getExceptions()),
            bufferMinutes: $availability->getBufferMinutes(),
            minNoticeHours: $availability->getMinNoticeHours(),
            bookingWindowDays: $availability->getBookingWindowDays(),
            clientCancelHours: $availability->getClientCancelHours(),
            reminderHours: $availability->getReminderHours(),
            meetingLink: $availability->getMeetingLink(),
            timezone: $account->getTimezone(),
        );
    }

    public static function session(BookingSession $session): SessionOutput
    {
        $contact = $session->getContact();

        return new SessionOutput(
            id: (string) $session->getId(),
            startsAt: (string) self::timestamp($session->getStartsAt()),
            endsAt: (string) self::timestamp($session->getEndsAt()),
            status: $session->getStatus()->value,
            contact: new ContactRefOutput(id: (string) $contact->getId(), fullName: $contact->getFullName(), email: $contact->getEmail()),
            planName: $session->getEnrollment()->getPlanName(),
            durationMinutes: $session->getEnrollment()->getDurationMinutes(),
            meetingLink: $session->getMeetingLink(),
            cancelReason: $session->getCancelReason(),
            bookedBy: $session->getBookedBy(),
        );
    }

    /**
     * Free slots grouped by the local day they fall on.
     *
     * @param list<\DateTimeImmutable> $slots
     *
     * @return list<SlotDayOutput>
     */
    public static function slotDays(Account $account, array $slots): array
    {
        $days = [];
        foreach ($slots as $slot) {
            $days[SessionTime::date($account, $slot)][] = new SlotOutput(startsAt: (string) self::timestamp($slot), label: SessionTime::time($account, $slot));
        }

        return array_map(static fn (string $label, array $s) => new SlotDayOutput(label: $label, slots: $s), array_keys($days), array_values($days));
    }

    /**
     * @param list<LeadSubmission>   $submissions
     * @param list<BookingSession>   $sessions
     * @param list<EnrollmentOutput> $enrollments
     */
    public static function contactDetail(Contact $contact, array $submissions, array $sessions = [], array $enrollments = [], ?PortalAccessOutput $portal = null): ContactDetailOutput
    {
        return new ContactDetailOutput(
            id: (string) $contact->getId(),
            fullName: $contact->getFullName(),
            email: $contact->getEmail(),
            phone: $contact->getPhone(),
            status: $contact->getStatus()->value,
            category: self::categoryRef($contact->getCategory()),
            sourcePage: null === $contact->getSourcePage() ? null : self::pageRef($contact->getSourcePage()),
            createdAt: (string) self::timestamp($contact->getCreatedAt()),
            lastActivityAt: (string) self::timestamp($contact->getLastActivityAt()),
            consentAt: (string) self::timestamp($contact->getConsentAt()),
            anonymized: $contact->isAnonymized(),
            submissions: array_map(static fn (LeadSubmission $s) => new SubmissionOutput(
                id: (string) $s->getId(),
                submittedAt: (string) self::timestamp($s->getSubmittedAt()),
                page: self::pageRef($s->getPage()),
                answers: array_map(static fn (array $a) => new AnswerOutput(key: $a['key'], label: $a['label'], value: $a['value']), $s->getAnswers()),
                utm: array_map(static fn (string $name, string $value) => new UtmOutput(name: $name, value: $value), array_keys($s->getUtm()), array_values($s->getUtm())),
                referrer: $s->getReferrer(),
            ), $submissions),
            sessions: array_map(self::session(...), $sessions),
            enrollments: $enrollments,
            portal: $portal ?? new PortalAccessOutput(status: 'none', lastSignInAt: null),
        );
    }

    public static function clientFile(ClientFile $file): ClientFileOutput
    {
        return new ClientFileOutput(
            id: (string) $file->getId(),
            name: $file->getName(),
            contentType: $file->getContentType(),
            sizeBytes: $file->getSizeBytes(),
            shared: $file->isShared(),
            active: $file->isActive(),
            byClient: $file->isByClient(),
            uploadedBy: $file->getUploadedBy()->getFullName(),
            createdAt: (string) self::timestamp($file->getCreatedAt()),
        );
    }

    public static function portalSession(BookingSession $session, bool $canChange): PortalSessionOutput
    {
        return new PortalSessionOutput(
            id: (string) $session->getId(),
            startsAt: (string) self::timestamp($session->getStartsAt()),
            endsAt: (string) self::timestamp($session->getEndsAt()),
            status: $session->getStatus()->value,
            planName: $session->getEnrollment()->getPlanName(),
            durationMinutes: $session->getEnrollment()->getDurationMinutes(),
            meetingLink: $session->getMeetingLink(),
            cancelReason: $session->getCancelReason(),
            canChange: $canChange,
        );
    }

    /**
     * @param array{taken: int, used: int} $counts
     * @param list<Payment>                $payments this plan's, newest first
     */
    public static function portalPlan(Enrollment $enrollment, array $counts, bool $payable, array $payments): PortalPlanOutput
    {
        return new PortalPlanOutput(
            id: (string) $enrollment->getId(),
            planName: $enrollment->getPlanName(),
            price: new MoneyOutput(amount: $enrollment->getPrice(), currency: $enrollment->getCurrency()),
            free: $enrollment->isFree(),
            sessionsIncluded: $enrollment->getSessionsIncluded(),
            sessionsTaken: $counts['taken'],
            sessionsUsed: $counts['used'],
            durationMinutes: $enrollment->getDurationMinutes(),
            status: $enrollment->getStatus()->value,
            payable: $payable,
            createdAt: (string) self::timestamp($enrollment->getCreatedAt()),
            payments: array_map(static fn (Payment $p) => new PortalPaymentOutput(
                reference: $p->getReference(),
                amount: new MoneyOutput(amount: $p->getAmount(), currency: $p->getCurrency()),
                status: $p->getStatus()->value,
                method: $p->getMethod(),
                manual: $p->isManual(),
                createdAt: (string) self::timestamp($p->getCreatedAt()),
                paidAt: self::timestamp($p->getPaidAt()),
            ), $payments),
        );
    }

    public static function portalNote(SessionNote $note): PortalNoteOutput
    {
        $session = $note->getSession();

        return new PortalNoteOutput(
            id: (string) $note->getId(),
            body: $note->getBody(),
            author: $note->getAuthor()->getFullName(),
            createdAt: (string) self::timestamp($note->getCreatedAt()),
            sessionId: (string) $session->getId(),
            sessionStartsAt: (string) self::timestamp($session->getStartsAt()),
            planName: $session->getEnrollment()->getPlanName(),
        );
    }

    public static function payment(Payment $payment): PaymentOutput
    {
        return new PaymentOutput(
            id: (string) $payment->getId(),
            reference: $payment->getReference(),
            amount: new MoneyOutput(amount: $payment->getAmount(), currency: $payment->getCurrency()),
            status: $payment->getStatus()->value,
            method: $payment->getMethod(),
            manual: $payment->isManual(),
            note: $payment->getNote(),
            recordedBy: $payment->getRecordedBy()?->getFullName(),
            contact: new ContactRefOutput(id: (string) $payment->getContact()->getId(), fullName: $payment->getContact()->getFullName(), email: $payment->getContact()->getEmail()),
            planName: $payment->getEnrollment()->getPlanName(),
            enrollmentId: (string) $payment->getEnrollment()->getId(),
            createdAt: (string) self::timestamp($payment->getCreatedAt()),
            paidAt: self::timestamp($payment->getPaidAt()),
        );
    }

    /**
     * @param array{taken: int, used: int} $counts
     * @param list<Payment>                $payments this enrollment's, newest first
     */
    public static function enrollment(Enrollment $enrollment, array $counts, ?string $paymentUrl, array $payments): EnrollmentOutput
    {
        return new EnrollmentOutput(
            id: (string) $enrollment->getId(),
            planName: $enrollment->getPlanName(),
            price: new MoneyOutput(amount: $enrollment->getPrice(), currency: $enrollment->getCurrency()),
            free: $enrollment->isFree(),
            sessionsIncluded: $enrollment->getSessionsIncluded(),
            sessionsTaken: $counts['taken'],
            sessionsUsed: $counts['used'],
            durationMinutes: $enrollment->getDurationMinutes(),
            status: $enrollment->getStatus()->value,
            outcome: $enrollment->getOutcome()?->value,
            sourcePage: null === $enrollment->getSourcePage() ? null : self::pageRef($enrollment->getSourcePage()),
            createdAt: (string) self::timestamp($enrollment->getCreatedAt()),
            completedAt: self::timestamp($enrollment->getCompletedAt()),
            paymentUrl: $paymentUrl,
            payments: array_map(self::payment(...), $payments),
        );
    }

    public static function sessionNote(SessionNote $note, bool $editable): SessionNoteOutput
    {
        $author = $note->getAuthor();

        return new SessionNoteOutput(
            id: (string) $note->getId(),
            body: $note->getBody(),
            visibility: $note->getVisibility()->value,
            author: new PersonOutput(id: (string) $author->getId(), fullName: $author->getFullName(), email: $author->getEmail()),
            createdAt: (string) self::timestamp($note->getCreatedAt()),
            updatedAt: (string) self::timestamp($note->getUpdatedAt()),
            editable: $editable,
        );
    }

    public static function wompiSettings(WompiSettings $settings, string $eventsUrl): WompiSettingsOutput
    {
        return new WompiSettingsOutput(
            publicKey: $settings->getPublicKey(),
            mode: $settings->getMode(),
            privateKeyEnding: $settings->getEnding('privateKey'),
            eventsSecretEnding: $settings->getEnding('eventsSecret'),
            integritySecretEnding: $settings->getEnding('integritySecret'),
            configured: $settings->isConfigured(),
            eventsUrl: $eventsUrl,
        );
    }

    public static function teamMember(User $user): TeamMemberOutput
    {
        return new TeamMemberOutput(
            id: (string) $user->getId(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            role: $user->hasRole(User::ROLE_OWNER) ? 'owner' : 'assistant',
            active: $user->isActive(),
            loginStatus: $user->getLoginStatus(),
            lastSignInAt: self::timestamp($user->getLastSignInAt()),
        );
    }

    public static function impersonatableUser(User $user): ImpersonatableUserOutput
    {
        $account = $user->getAccount() ?? throw new \LogicException('Only a user with an account can be impersonated.');

        return new ImpersonatableUserOutput(
            id: (string) $user->getId(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            role: $user->hasRole(User::ROLE_OWNER) ? 'owner' : 'assistant',
            account: new ImpersonatableAccountOutput(id: (string) $account->getId(), name: $account->getName()),
        );
    }

    /** ISO 8601 in UTC, the API's one timestamp format. */
    public static function timestamp(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM);
    }

    /**
     * @param array<string, array<string, mixed>> $fields an "items" field's item fields
     *
     * @return list<ItemFieldSpecOutput>
     */
    private static function itemFields(array $fields): array
    {
        $specs = [];
        foreach ($fields as $name => $spec) {
            $specs[] = new ItemFieldSpecOutput(name: $name, kind: (string) $spec['kind'], required: true === ($spec['required'] ?? false), max: isset($spec['max']) ? (int) $spec['max'] : null);
        }

        return $specs;
    }

    /**
     * @param list<AccountFeature> $features
     *
     * @return list<string>
     */
    private static function featureValues(array $features): array
    {
        return array_map(static fn (AccountFeature $f) => $f->value, $features);
    }

    /** A setting's value as the change log shows it: a list comma-separated. */
    private static function settingText(mixed $value): string
    {
        return \is_array($value) ? implode(', ', array_map(strval(...), $value)) : (string) $value;
    }
}
