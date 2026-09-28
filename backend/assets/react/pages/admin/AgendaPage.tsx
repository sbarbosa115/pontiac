import React, { useState } from 'react';
import { useTabParam } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import type { IconName } from '../../components/Icon';
import { BookSessionModal } from '../../components/SessionModals';
import { PageHeader, TabPanel, Tabs } from '../../components/ui';
import AvailabilityForm from './agenda/AvailabilityForm';
import SessionsList from './agenda/SessionsList';
import WeekView from './agenda/WeekView';

const TABS: { value: string; icon: IconName }[] = [
    { value: 'semana', icon: 'calendar' },
    { value: 'sesiones', icon: 'clipboard' },
    { value: 'disponibilidad', icon: 'clock' },
];

/** Agenda: the week's sessions, every session, and when the consultant takes them. The tab is in the URL (?tab=). */
export default function AgendaPage() {
    const [tab, setTab] = useTabParam(TABS.map(({ value }) => value));
    const [booking, setBooking] = useState(false);
    // Bumped after a booking, so the tab showing reloads its sessions.
    const [version, setVersion] = useState(0);

    return (
        <>
            <PageHeader title={t('nav.agenda')} subtitle={t('agenda.subtitle')} />
            <Tabs
                id="agenda"
                variant="page"
                label={t('nav.agenda')}
                value={tab}
                onChange={setTab}
                options={TABS.map(({ value, icon }) => ({ value, icon, label: t(`agenda.tab.${value}`) }))}
            />
            <TabPanel id="agenda" value={tab}>
                {tab === 'semana' && <WeekView key={version} onBook={() => setBooking(true)} />}
                {tab === 'sesiones' && <SessionsList key={version} onBook={() => setBooking(true)} />}
                {tab === 'disponibilidad' && <AvailabilityForm />}
            </TabPanel>
            {booking && (
                <BookSessionModal
                    onClose={() => setBooking(false)}
                    onBooked={() => {
                        setBooking(false);
                        setVersion((current) => current + 1);
                    }}
                />
            )}
        </>
    );
}
