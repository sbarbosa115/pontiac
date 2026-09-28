import React from 'react';
import { addDays, SESSION_STATUSES, type Session } from '../../../lib/agenda';
import { useLocaleSettings } from '../../../lib/auth';
import { formatDateTime, todayIn } from '../../../lib/format';
import { useList } from '../../../lib/hooks';
import { t } from '../../../lib/i18n';
import { useSessionActions } from '../../../components/SessionModals';
import { Actions, Alert, Button, FilterBar, ListView, Row, RowLegend } from '../../../components/ui';

/** Agenda › Sesiones: every session, searched by the person, filtered by status and by upcoming or past. */
export default function SessionsList({ onBook }: { onBook: () => void }) {
    const { locale, timezone } = useLocaleSettings();
    const today = todayIn(timezone);
    const list = useList<Session>('/api/admin/sessions', { q: '', status: '', from: today, to: '' });
    const actions = useSessionActions(() => list.reload());
    const when = list.filters.from ? 'upcoming' : list.filters.to ? 'past' : 'all';

    const bookButton = <Button onClick={onBook}>{t('agenda.book')}</Button>;

    return (
        <>
            <FilterBar
                search={list.filters.q}
                onSearch={(q) => list.update({ q })}
                searchPlaceholder={t('agenda.searchPlaceholder')}
                filters={[
                    {
                        name: 'when',
                        label: t('agenda.when'),
                        value: when,
                        onChange: (value) => list.update(value === 'upcoming' ? { from: today, to: '' } : value === 'past' ? { from: '', to: addDays(today, -1) } : { from: '', to: '' }),
                        options: [
                            { value: 'upcoming', label: t('agenda.upcoming') },
                            { value: 'past', label: t('agenda.past') },
                            { value: 'all', label: t('agenda.allDates') },
                        ],
                    },
                    {
                        name: 'status',
                        label: t('agenda.status'),
                        value: list.filters.status,
                        onChange: (status) => list.update({ status }),
                        options: [{ value: '', label: t('agenda.allStatuses') }, ...SESSION_STATUSES.map((status) => ({ value: status, label: t(`agenda.statusName.${status}`) }))],
                    },
                ]}
            >
                {bookButton}
            </FilterBar>
            <Alert kind="error" onDismiss={actions.clearError}>
                {actions.error}
            </Alert>
            <RowLegend statuses={SESSION_STATUSES.map((status) => ({ value: status, label: t(`agenda.statusName.${status}`) }))} />
            <ListView
                list={list}
                empty={t('agenda.empty')}
                showAll={{ q: '', status: '', from: '', to: '' }}
                emptyAll={t('agenda.emptyAll')}
                emptyAction={bookButton}
                columns={[t('agenda.startsAt'), t('agenda.contact'), t('agenda.plan'), t('agenda.bookedBy')]}
                renderRow={(session) => (
                    <Row key={session.id} status={session.status} label={t(`agenda.statusName.${session.status}`)}>
                        <td className="strong">{formatDateTime(session.startsAt, locale, timezone)}</td>
                        <td>
                            {session.contact.fullName}
                            <div className="small muted">{session.contact.email}</div>
                        </td>
                        <td>
                            {session.planName}
                            {session.cancelReason && <div className="small muted">{session.cancelReason}</div>}
                        </td>
                        <td>{t(`agenda.bookedByName.${session.bookedBy}`)}</td>
                        <Actions>{actions.buttons(session)}</Actions>
                    </Row>
                )}
            />
            {actions.modals}
        </>
    );
}
