<?php

declare(strict_types=1);

namespace App\Platform;

use App\Api\Input\PlatformSettingsInput;
use App\Entity\PlatformSettings;
use App\Entity\PlatformSettingsChange;
use App\Entity\User;
use App\Enum\AccountFeature;
use App\Enum\PageTemplate;
use App\Repository\PlatformSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Saves what a super admin changed in Configuración, and records who changed what (Historial). A field left out of
 * the request is left as it is; a save that changes nothing records nothing.
 */
final class PlatformSettingsEditor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlatformSettingsRepository $repository,
    ) {
    }

    public function apply(PlatformSettingsInput $input, User $by): PlatformSettings
    {
        $settings = $this->repository->current();
        $before = $settings->snapshot();

        $settings->setGeneral(
            $input->platformName ?? $settings->getPlatformName(),
            $input->supportEmail ?? $settings->getSupportEmail(),
            $input->senderName ?? $settings->getSenderName(),
        );
        if (null !== $input->enabledTemplates) {
            $settings->setEnabledTemplates(array_map(PageTemplate::from(...), $input->enabledTemplates));
        }
        $settings->setBookingDefaults(
            $input->reminderHours ?? $settings->getReminderHours(),
            $input->minNoticeHours ?? $settings->getMinNoticeHours(),
            $input->bookingWindowDays ?? $settings->getBookingWindowDays(),
            $input->clientCancelHours ?? $settings->getClientCancelHours(),
            $input->sessionBufferMinutes ?? $settings->getSessionBufferMinutes(),
        );
        $settings->setDefaultLimits(
            $input->defaultMaxPublishedPages ?? $settings->getDefaultMaxPublishedPages(),
            $input->defaultMaxAssistants ?? $settings->getDefaultMaxAssistants(),
            $input->defaultStorageMb ?? $settings->getDefaultStorageMb(),
            $input->defaultMaxFileMb ?? $settings->getDefaultMaxFileMb(),
        );
        if (null !== $input->defaultFeatures) {
            $settings->setDefaultFeatures(array_map(AccountFeature::from(...), $input->defaultFeatures));
        }
        $settings->setLegal(
            $input->termsText ?? $settings->getTermsText(),
            $input->defaultPrivacyText ?? $settings->getDefaultPrivacyText(),
            $input->reservedSlugs ?? $settings->getReservedSlugs(),
        );

        $changes = [];
        foreach ($settings->snapshot() as $field => $value) {
            if ($before[$field] !== $value) {
                $changes[$field] = ['from' => $before[$field], 'to' => $value];
            }
        }
        if ([] === $changes) {
            return $settings;
        }

        $settings->touch();
        $this->em->persist($settings);
        $this->em->persist(new PlatformSettingsChange($by, $changes));
        $this->em->flush();

        return $settings;
    }
}
