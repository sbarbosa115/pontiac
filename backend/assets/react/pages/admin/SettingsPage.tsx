import React from 'react';
import { ROLE_OWNER, useAuth } from '../../lib/auth';
import { useTabParam } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import type { IconName } from '../../components/Icon';
import { PageHeader, TabPanel, Tabs } from '../../components/ui';
import CategoriesPage from './CategoriesPage';
import EmailTemplatesPage from './EmailTemplatesPage';
import MediaPage from './MediaPage';
import PrivacyPage from './PrivacyPage';
import TeamPage from './TeamPage';
import WompiSettingsPage from './WompiSettingsPage';

// The tabs Ajustes has today; the PRD adds Perfil. Pagos Wompi is the owner's, with payments on; Correos needs flows.
const TABS: { value: string; icon: IconName; ownerOnly?: boolean; feature?: string }[] = [
    { value: 'equipo', icon: 'users' },
    { value: 'categorias', icon: 'tag' },
    { value: 'medios', icon: 'paperclip' },
    { value: 'correos', icon: 'inbox', feature: 'flows' },
    { value: 'pagos', icon: 'card', ownerOnly: true, feature: 'payments' },
    { value: 'privacidad', icon: 'shield' },
];

/** Ajustes: the practice's own settings. Every tab is in the URL (?tab=), so a link lands on it. */
export default function SettingsPage() {
    const { roles, me } = useAuth();
    const features = me?.account?.features ?? [];
    const tabs = TABS.filter((option) => (!option.ownerOnly || roles.includes(ROLE_OWNER)) && (!option.feature || features.includes(option.feature)));
    const [tab, setTab] = useTabParam(tabs.map(({ value }) => value));

    return (
        <>
            <PageHeader title={t('nav.settings')} subtitle={t('settings.subtitle')} />
            <Tabs
                id="settings"
                variant="page"
                label={t('nav.settings')}
                value={tab}
                onChange={setTab}
                options={tabs.map(({ value, icon }) => ({ value, icon, label: t(`settings.tab.${value}`) }))}
            />
            <TabPanel id="settings" value={tab}>
                {tab === 'equipo' && <TeamPage embedded />}
                {tab === 'categorias' && <CategoriesPage />}
                {tab === 'medios' && <MediaPage />}
                {tab === 'correos' && <EmailTemplatesPage />}
                {tab === 'pagos' && <WompiSettingsPage />}
                {tab === 'privacidad' && <PrivacyPage />}
            </TabPanel>
        </>
    );
}
