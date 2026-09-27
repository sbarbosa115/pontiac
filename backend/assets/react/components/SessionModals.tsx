import React, { type ReactNode, useState } from 'react';
import { api } from '../lib/api';
import { type Session, sessionActions } from '../lib/agenda';
import { useLocaleSettings } from '../lib/auth';
import { formatDateTime } from '../lib/format';
import { useApi, useSubmit } from '../lib/hooks';
import { rowErrorMessage, t } from '../lib/i18n';
import type { Get, Schema } from '../lib/types';
import Icon from './Icon';
import { Alert, Field, FormModal, IconButton, Loading } from './ui';

type SlotDay = Schema<'SlotDayOutput'>;
type Contact = Schema<'ContactSummaryOutput'>;

/**
 * The free slots for a plan (to book) or for a session (to move it), as a day and then a time. The value is the
 * slot's ISO start, as the API takes it. Give it a `key` per plan or session, so another one starts on its first day.
 */
function SlotPicker({ query, value, onChange, error }: { query: { planId: string } | { sessionId: string }; value: string; onChange: (startsAt: string) => void; error?: string }) {
    const key = 'planId' in query ? query.planId : query.sessionId;
    const slots = useApi(() => (key ? api.get<Get<'/api/admin/availability/slots'>>('/api/admin/availability/slots', query) : Promise.resolve({ days: [] as SlotDay[] })), [key]);
    const days = slots.data?.days ?? [];
    const [dayIndex, setDayIndex] = useState(0);
    const day = days[dayIndex];

    if (slots.loading && !slots.data) return <Loading />;
    if (key && days.length === 0) return <Alert kind="warning">{t('agenda.noSlots')}</Alert>;

    return (
        <>
            <Field label={t('agenda.day')}>
                <select
                    value={dayIndex}
                    disabled={days.length === 0}
                    onChange={(event) => {
                        setDayIndex(Number(event.target.value));
                        onChange('');
                    }}
                >
                    {days.map((option, index) => (
                        <option key={option.label} value={index}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label={t('agenda.time')} error={error}>
                <select value={value} disabled={!day} onChange={(event) => onChange(event.target.value)}>
                    <option value="">{t('agenda.pickTime')}</option>
                    {(day?.slots ?? []).map((slot) => (
                        <option key={slot.startsAt} value={slot.startsAt}>
                            {slot.label}
                        </option>
                    ))}
                </select>
            </Field>
        </>
    );
}

/** "Agendar sesión": a free plan's session for a contact, at one of the consultant's free slots. */
export function BookSessionModal({ contact, onClose, onBooked }: { contact?: { id: string; fullName: string }; onClose: () => void; onBooked: (session: Session) => void }) {
    const plans = useApi(() => api.get<Get<'/api/admin/plans/all'>>('/api/admin/plans/all'), []);
    const bookable = (plans.data?.items ?? []).filter((plan) => plan.active && plan.free);
    const [contactId, setContactId] = useState(contact?.id ?? '');
    const [planId, setPlanId] = useState('');
    const [startsAt, setStartsAt] = useState('');
    const submit = useSubmit();
    const chosenPlan = planId || bookable[0]?.id || '';

    const save = async () => {
        const result = await submit.run(() => api.post<Session>('/api/admin/sessions', { contactId, planId: chosenPlan, startsAt }));
        if (result.ok) onBooked(result.value);
    };

    return (
        <FormModal title={t('agenda.book')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('agenda.bookSubmit')}>
            {contact ? (
                <Field label={t('agenda.contact')}>
                    <input value={contact.fullName} readOnly />
                </Field>
            ) : (
                <ContactSearch value={contactId} onChange={setContactId} error={submit.errors.contactId} />
            )}
            {plans.data && bookable.length === 0 ? (
                <Alert kind="warning">{t('agenda.noFreePlans')}</Alert>
            ) : (
                <>
                    <Field label={t('agenda.plan')} error={submit.errors.planId} hint={t('agenda.planHint')}>
                        <select
                            value={chosenPlan}
                            onChange={(event) => {
                                setPlanId(event.target.value);
                                setStartsAt('');
                            }}
                        >
                            {bookable.map((plan) => (
                                <option key={plan.id} value={plan.id}>
                                    {t('agenda.planOption', { name: plan.name, minutes: plan.durationMinutes })}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <SlotPicker key={chosenPlan} query={{ planId: chosenPlan }} value={startsAt} onChange={setStartsAt} error={submit.errors.startsAt} />
                </>
            )}
        </FormModal>
    );
}

/** A contact picked by name or email among the consultant's. */
function ContactSearch({ value, onChange, error }: { value: string; onChange: (id: string) => void; error?: string }) {
    const [q, setQ] = useState('');
    const found = useApi(() => api.get<Get<'/api/admin/contacts'>>('/api/admin/contacts', { q, perPage: 8 }), [q]);
    const options: Contact[] = found.data?.items ?? [];

    return (
        <>
            <Field label={t('agenda.findContact')}>
                <input type="search" value={q} placeholder={t('agenda.findContactPlaceholder')} onChange={(event) => setQ(event.target.value)} />
            </Field>
            <Field label={t('agenda.contact')} error={error}>
                <select value={value} onChange={(event) => onChange(event.target.value)}>
                    <option value="">{options.length === 0 ? t('agenda.noContacts') : t('agenda.pickContact')}</option>
                    {options.map((contact) => (
                        <option key={contact.id} value={contact.id}>
                            {contact.fullName} · {contact.email}
                        </option>
                    ))}
                </select>
            </Field>
        </>
    );
}

/** "Reprogramar": the session to another free slot. The person gets the new time and a new link. */
export function RescheduleModal({ session, onClose, onDone }: { session: Session; onClose: () => void; onDone: (session: Session) => void }) {
    const { locale, timezone } = useLocaleSettings();
    const [startsAt, setStartsAt] = useState('');
    const submit = useSubmit();

    const save = async () => {
        const result = await submit.run(() => api.post<Session>(`/api/admin/sessions/${session.id}/reschedule`, { startsAt }));
        if (result.ok) onDone(result.value);
    };

    return (
        <FormModal title={t('agenda.reschedule')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('agenda.rescheduleSubmit')}>
            <p className="small muted">{t('agenda.rescheduleIntro', { name: session.contact.fullName, when: formatDateTime(session.startsAt, locale, timezone) })}</p>
            <SlotPicker query={{ sessionId: session.id }} value={startsAt} onChange={setStartsAt} error={submit.errors.startsAt} />
        </FormModal>
    );
}

/** "Cancelar sesión", with a reason the person reads in the email. */
export function CancelSessionModal({ session, onClose, onDone }: { session: Session; onClose: () => void; onDone: (session: Session) => void }) {
    const { locale, timezone } = useLocaleSettings();
    const [reason, setReason] = useState('');
    const submit = useSubmit();

    const save = async () => {
        const result = await submit.run(() => api.post<Session>(`/api/admin/sessions/${session.id}/cancel`, { reason }));
        if (result.ok) onDone(result.value);
    };

    return (
        <FormModal title={t('agenda.cancel')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('agenda.cancelSubmit')}>
            <p className="small muted">{t('agenda.cancelIntro', { name: session.contact.fullName, when: formatDateTime(session.startsAt, locale, timezone) })}</p>
            <Field label={t('agenda.cancelReason')} error={submit.errors.reason} optional hint={t('agenda.cancelReasonHint')}>
                <textarea value={reason} maxLength={500} rows={3} onChange={(event) => setReason(event.target.value)} />
            </Field>
        </FormModal>
    );
}

/**
 * A session's row actions and the modals they open, for any list of sessions: move, cancel, done, no-show, reopen.
 * `onChanged` gets the session as it is now; `error` says why the last action failed.
 */
export function useSessionActions(onChanged: (session: Session) => void) {
    const [moving, setMoving] = useState<Session | null>(null);
    const [cancelling, setCancelling] = useState<Session | null>(null);
    const [busy, setBusy] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const post = async (session: Session, action: 'done' | 'no-show' | 'reopen') => {
        setBusy(`${session.id}:${action}`);
        setError(null);
        try {
            onChanged(await api.post<Session>(`/api/admin/sessions/${session.id}/${action}`));
        } catch (err) {
            setError(rowErrorMessage(err));
        } finally {
            setBusy(null);
        }
    };

    const buttons = (session: Session): ReactNode => {
        const can = sessionActions(session);
        return (
            <>
                {can.close && <IconButton icon="check" label={t('agenda.markDone')} busy={busy === `${session.id}:done`} onClick={() => post(session, 'done')} />}
                {can.close && <IconButton icon="ban" action="revert" label={t('agenda.markNoShow')} busy={busy === `${session.id}:no-show`} onClick={() => post(session, 'no-show')} />}
                {can.reschedule && <IconButton icon="clock" action="edit" label={t('agenda.reschedule')} onClick={() => setMoving(session)} />}
                {can.cancel && <IconButton icon="close" label={t('agenda.cancel')} onClick={() => setCancelling(session)} />}
                {can.reopen && <IconButton icon="undo" label={t('agenda.reopen')} busy={busy === `${session.id}:reopen`} onClick={() => post(session, 'reopen')} />}
                {session.meetingLink && session.status === 'scheduled' && (
                    <a className="btn btn-action btn-action-open btn-icon" href={session.meetingLink} target="_blank" rel="noopener noreferrer" aria-label={t('agenda.openMeeting')} data-tooltip={t('agenda.openMeeting')}>
                        <Icon name="globe" size={16} />
                    </a>
                )}
            </>
        );
    };

    const done = (session: Session) => {
        setMoving(null);
        setCancelling(null);
        onChanged(session);
    };

    const modals = (
        <>
            {moving && <RescheduleModal session={moving} onClose={() => setMoving(null)} onDone={done} />}
            {cancelling && <CancelSessionModal session={cancelling} onClose={() => setCancelling(null)} onDone={done} />}
        </>
    );

    return { buttons, modals, error, clearError: () => setError(null) };
}
