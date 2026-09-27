<?php

declare(strict_types=1);

namespace App\Api;

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
