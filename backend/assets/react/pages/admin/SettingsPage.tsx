import React from 'react';
import { useTabParam } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import type { IconName } from '../../components/Icon';
import { PageHeader, TabPanel, Tabs } from '../../components/ui';
import TeamPage from './TeamPage';

// The tabs Ajustes has today; the PRD adds Perfil, Pagos Wompi, Correos, Categorías and Medios.
const TABS: { value: string; icon: IconName }[] = [{ value: 'equipo', icon: 'users' }];

/** Ajustes: the practice's own settings. Every tab is in the URL (?tab=), so a link lands on it. */
export default function SettingsPage() {
    const [tab, setTab] = useTabParam(TABS.map(({ value }) => value));

    return (
        <>
            <PageHeader title={t('nav.settings')} subtitle={t('settings.subtitle')} />
            <Tabs
                id="settings"
                variant="page"
                label={t('nav.settings')}
                value={tab}
                onChange={setTab}
                options={TABS.map(({ value, icon }) => ({ value, icon, label: t(`settings.tab.${value}`) }))}
            />
            <TabPanel id="settings" value={tab}>
                {tab === 'equipo' && <TeamPage embedded />}
            </TabPanel>
        </>
    );
}
