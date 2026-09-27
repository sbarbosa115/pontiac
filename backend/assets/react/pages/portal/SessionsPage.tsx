import React, { useState } from 'react';
import { api } from '../../lib/api';
import { SESSION_STATUSES } from '../../lib/agenda';
import { useLocaleSettings } from '../../lib/auth';
import { formatDateTime } from '../../lib/format';
import { useApi, useSubmit } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import { bookablePlans, type PortalSession, splitSessions } from '../../lib/portal';
import type { Get } from '../../lib/types';
import { SlotPicker } from '../../components/SessionModals';
import { ActionButton, Actions, actionClass, Alert, Button, DataTable, EmptyState, ErrorState, Field, FormModal, Loading, PageHeader, Row, RowLegend, TabIntro } from '../../components/ui';

/** Sesiones: book one of their plans' sessions, move or cancel one until the limit, see the past ones. */
export default function SessionsPage() {
    const { locale, timezone } = useLocaleSettings();
    const sessions = useApi(() => api.get<Get<'/api/portal/sessions'>>('/api/portal/sessions'), []);
    const plans = useApi(() => api.get<Get<'/api/portal/plans'>>('/api/portal/plans'), []);
    const [booking, setBooking] = useState(false);
    const [moving, setMoving] = useState<PortalSession | null>(null);
    const [cancelling, setCancelling] = useState<PortalSession | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    if (sessions.error) return <ErrorState error={sessions.error} onRetry={sessions.reload} />;
    if (!sessions.data || !plans.data) return <Loading />;

    const bookable = bookablePlans(plans.data.items);
    const { upcoming, past } = splitSessions(sessions.data.items);
    const done = (message: string) => {
        setBooking(false);
        setMoving(null);
        setCancelling(null);
        setNotice(message);
        sessions.reload();
        plans.reload();
    };
    const bookButton = bookable.length > 0 ? <Button onClick={() => setBooking(true)}>{t('portal.book')}</Button> : null;

    const table = (rows: PortalSession[]) => (
        <DataTable
            columns={[t('agenda.startsAt'), t('agenda.plan')]}
            rows={rows}
            renderRow={(session) => (
                <Row key={session.id} status={session.status} label={t(`agenda.statusName.${session.status}`)}>
                    <td className="strong">{formatDateTime(session.startsAt, locale, timezone)}</td>
                    <td>
                        {session.planName}
                        {session.cancelReason && <div className="small muted">{session.cancelReason}</div>}
                    </td>
                    <Actions>
                        {session.status === 'scheduled' && session.meetingLink && (
                            <a className={actionClass('open')} href={session.meetingLink} target="_blank" rel="noopener noreferrer">
                                {t('portal.join')}
                            </a>
                        )}
                        {session.canChange && (
                            <ActionButton action="edit" onClick={() => setMoving(session)}>
                                {t('agenda.reschedule')}
                            </ActionButton>
                        )}
                        {session.canChange && (
                            <ActionButton action="danger" onClick={() => setCancelling(session)}>
                                {t('agenda.cancelShort')}
                            </ActionButton>
                        )}
                    </Actions>
                </Row>
            )}
        />
    );

    return (
        <>
            <PageHeader title={t('portal.sessions')} subtitle={t('portal.sessionsSubtitle')} />
            <TabIntro action={bookButton}>{bookable.length > 0 ? t('portal.sessionsIntro') : t('portal.sessionsNoPlan')}</TabIntro>
            <Alert kind="success" onDismiss={() => setNotice(null)}>
                {notice}
            </Alert>
            <RowLegend statuses={SESSION_STATUSES.map((status) => ({ value: status, label: t(`agenda.statusName.${status}`) }))} />
            <h2 className="section-title">{t('portal.upcoming')}</h2>
            {upcoming.length === 0 ? <EmptyState action={bookButton}>{t('portal.noUpcoming')}</EmptyState> : table(upcoming)}
            {past.length > 0 && (
                <>
                    <h2 className="section-title">{t('portal.past')}</h2>
                    {table(past)}
                </>
            )}
            {booking && <BookModal plans={bookable} onClose={() => setBooking(false)} onDone={() => done(t('portal.booked'))} />}
            {moving && <MoveModal session={moving} onClose={() => setMoving(null)} onDone={() => done(t('portal.moved'))} />}
            {cancelling && <CancelModal session={cancelling} onClose={() => setCancelling(null)} onDone={() => done(t('portal.cancelled'))} />}
        </>
    );
}

function BookModal({ plans, onClose, onDone }: { plans: ReturnType<typeof bookablePlans>; onClose: () => void; onDone: () => void }) {
    const [planId, setPlanId] = useState(plans[0]?.id ?? '');
    const [startsAt, setStartsAt] = useState('');
    const submit = useSubmit();
    const save = async () => {
        const result = await submit.run(() => api.post('/api/portal/sessions', { enrollmentId: planId, startsAt }));
        if (result.ok) onDone();
    };

    return (
        <FormModal title={t('portal.book')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('agenda.bookSubmit')}>
            <Field className="span-2" label={t('agenda.plan')} error={submit.errors.enrollmentId}>
                <select
                    value={planId}
                    onChange={(event) => {
                        setPlanId(event.target.value);
                        setStartsAt('');
                    }}
                >
                    {plans.map((plan) => (
                        <option key={plan.id} value={plan.id}>
                            {t('portal.planOption', { name: plan.planName, left: plan.sessionsIncluded - plan.sessionsTaken, minutes: plan.durationMinutes })}
                        </option>
                    ))}
                </select>
            </Field>
            <SlotPicker key={planId} endpoint="/api/portal/slots" query={{ enrollmentId: planId }} value={startsAt} onChange={setStartsAt} error={submit.errors.startsAt} />
        </FormModal>
    );
}

function MoveModal({ session, onClose, onDone }: { session: PortalSession; onClose: () => void; onDone: () => void }) {
    const { locale, timezone } = useLocaleSettings();
    const [startsAt, setStartsAt] = useState('');
    const submit = useSubmit();
    const save = async () => {
        const result = await submit.run(() => api.post(`/api/portal/sessions/${session.id}/reschedule`, { startsAt }));
        if (result.ok) onDone();
    };

    return (
        <FormModal title={t('agenda.reschedule')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('agenda.rescheduleSubmit')}>
            <p className="small muted span-2">{t('portal.moveIntro', { when: formatDateTime(session.startsAt, locale, timezone) })}</p>
            <SlotPicker endpoint="/api/portal/slots" query={{ sessionId: session.id }} value={startsAt} onChange={setStartsAt} error={submit.errors.startsAt} />
        </FormModal>
    );
}

function CancelModal({ session, onClose, onDone }: { session: PortalSession; onClose: () => void; onDone: () => void }) {
    const { locale, timezone } = useLocaleSettings();
    const [reason, setReason] = useState('');
    const submit = useSubmit();
    const save = async () => {
        const result = await submit.run(() => api.post(`/api/portal/sessions/${session.id}/cancel`, { reason }));
        if (result.ok) onDone();
    };

    return (
        <FormModal title={t('agenda.cancel')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('agenda.cancelSubmit')}>
            <p className="small muted span-2">{t('portal.cancelIntro', { when: formatDateTime(session.startsAt, locale, timezone) })}</p>
            <Field className="span-2" label={t('portal.cancelReason')} optional error={submit.errors.reason}>
                <textarea value={reason} rows={3} maxLength={500} onChange={(event) => setReason(event.target.value)} />
            </Field>
        </FormModal>
    );
}
