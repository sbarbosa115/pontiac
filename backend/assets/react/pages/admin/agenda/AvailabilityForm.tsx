import React, { useState } from 'react';
import { api } from '../../../lib/api';
import { parseHours, WEEKDAYS, weekdayName } from '../../../lib/agenda';
import { useLocaleSettings } from '../../../lib/auth';
import { useApi, useSubmit } from '../../../lib/hooks';
import { t } from '../../../lib/i18n';
import type { Schema } from '../../../lib/types';
import DateInput from '../../../components/DateInput';
import { ActionButton, Alert, Button, Checkbox, ErrorState, Field, IconButton, Loading, TabIntro } from '../../../components/ui';

type Availability = Schema<'AvailabilityOutput'>;
type Rule = Schema<'WeeklyRuleOutput'>;
type Exception = Schema<'AvailabilityExceptionOutput'>;

/** Agenda › Disponibilidad: loads, then hands the saved hours to the form. */
export default function AvailabilityForm() {
    const loaded = useApi(() => api.get<Availability>('/api/admin/availability'), []);

    if (loaded.error) return <ErrorState error={loaded.error} onRetry={loaded.reload} />;
    if (!loaded.data) return <Loading />;

    return <Editor initial={loaded.data} />;
}

/**
 * The weekly hours as ranges per day, the exceptions (a day off, or other hours that day), and the rules around
 * booking. Errors come back by position in the list (weeklyRules[3].from), so each range remembers its index.
 */
function Editor({ initial }: { initial: Availability }) {
    const { locale } = useLocaleSettings();
    const [rules, setRules] = useState<Rule[]>(initial.weeklyRules);
    const [exceptions, setExceptions] = useState<Exception[]>(initial.exceptions);
    const [numbers, setNumbers] = useState({
        bufferMinutes: String(initial.bufferMinutes),
        minNoticeHours: String(initial.minNoticeHours),
        bookingWindowDays: String(initial.bookingWindowDays),
        clientCancelHours: String(initial.clientCancelHours),
    });
    const [reminders, setReminders] = useState(initial.reminderHours.join(', '));
    const [meetingLink, setMeetingLink] = useState(initial.meetingLink);
    const [saved, setSaved] = useState(false);
    const submit = useSubmit();
    const errors = submit.errors;

    const setRule = (index: number, patch: Partial<Rule>) => setRules(rules.map((rule, i) => (i === index ? { ...rule, ...patch } : rule)));
    const setException = (index: number, patch: Partial<Exception>) => setExceptions(exceptions.map((exception, i) => (i === index ? { ...exception, ...patch } : exception)));

    const save = async () => {
        setSaved(false);
        const toNumber = (text: string) => (text.trim() === '' ? null : Number(text));
        const result = await submit.run(() =>
            api.put<Availability>('/api/admin/availability', {
                weeklyRules: rules,
                exceptions,
                bufferMinutes: toNumber(numbers.bufferMinutes),
                minNoticeHours: toNumber(numbers.minNoticeHours),
                bookingWindowDays: toNumber(numbers.bookingWindowDays),
                clientCancelHours: toNumber(numbers.clientCancelHours),
                reminderHours: parseHours(reminders),
                meetingLink,
            }),
        );
        if (result.ok) {
            // The API keeps them sorted: show them as they are now, so positions match any later error.
            setRules(result.value.weeklyRules);
            setExceptions(result.value.exceptions);
            setReminders(result.value.reminderHours.join(', '));
            setSaved(true);
        }
    };

    const numberField = (name: keyof typeof numbers, max: number) => (
        <Field label={t(`availability.${name}`)} error={errors[name]} hint={t(`availability.${name}Hint`)}>
            <input type="number" min={0} max={max} value={numbers[name]} onChange={(event) => setNumbers({ ...numbers, [name]: event.target.value })} />
        </Field>
    );

    return (
        <form
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                save();
            }}
        >
            <TabIntro>{t('availability.intro', { timezone: initial.timezone })}</TabIntro>
            <Alert kind="error">{submit.formError}</Alert>
            {saved && (
                <Alert kind="success" onDismiss={() => setSaved(false)}>
                    {t('availability.saved')}
                </Alert>
            )}

            <section className="card">
                <h2 className="section-title">{t('availability.weekly')}</h2>
                {errors.weeklyRules && <p className="field-error">{errors.weeklyRules}</p>}
                <div className="weekly-rules">
                    {WEEKDAYS.map((weekday) => {
                        const ranges = rules.map((rule, index) => ({ rule, index })).filter(({ rule }) => rule.weekday === weekday);
                        return (
                            <div key={weekday} className="weekly-day">
                                <span className="weekly-day-name strong">{weekdayName(weekday, locale)}</span>
                                <div className="weekly-day-ranges">
                                    {ranges.length === 0 && <span className="muted small">{t('availability.closed')}</span>}
                                    {ranges.map(({ rule, index }) => (
                                        <div key={index} className="time-range">
                                            <TimeInput label={t('availability.from')} value={rule.from} error={errors[`weeklyRules[${index}].from`] ?? errors[`weeklyRules[${index}].weekday`]} onChange={(from) => setRule(index, { from })} />
                                            <TimeInput label={t('availability.to')} value={rule.to} error={errors[`weeklyRules[${index}].to`]} onChange={(to) => setRule(index, { to })} />
                                            <IconButton icon="close" label={t('availability.removeRange')} onClick={() => setRules(rules.filter((_, i) => i !== index))} />
                                        </div>
                                    ))}
                                </div>
                                <ActionButton action="setup" onClick={() => setRules([...rules, { weekday, from: '09:00', to: '12:00' }])}>
                                    {t('availability.addRange')}
                                </ActionButton>
                            </div>
                        );
                    })}
                </div>
            </section>

            <section className="card">
                <h2 className="section-title">{t('availability.exceptions')}</h2>
                <p className="small muted">{t('availability.exceptionsIntro')}</p>
                {exceptions.map((exception, index) => {
                    const dayOff = exception.from === null;
                    return (
                        <div key={index} className="time-range">
                            <Field label={t('availability.date')} error={errors[`exceptions[${index}].date`]}>
                                <DateInput value={exception.date} onChange={(event) => setException(index, { date: event.target.value })} />
                            </Field>
                            <Checkbox label={t('availability.dayOff')} checked={dayOff} onChange={(off) => setException(index, off ? { from: null, to: null } : { from: '09:00', to: '12:00' })} />
                            {!dayOff && (
                                <>
                                    <TimeInput label={t('availability.from')} value={exception.from ?? ''} error={errors[`exceptions[${index}].from`]} onChange={(from) => setException(index, { from })} />
                                    <TimeInput label={t('availability.to')} value={exception.to ?? ''} error={errors[`exceptions[${index}].to`]} onChange={(to) => setException(index, { to })} />
                                </>
                            )}
                            <IconButton icon="close" label={t('availability.removeException')} onClick={() => setExceptions(exceptions.filter((_, i) => i !== index))} />
                        </div>
                    );
                })}
                <ActionButton action="setup" onClick={() => setExceptions([...exceptions, { date: '', from: null, to: null }])}>
                    {t('availability.addException')}
                </ActionButton>
            </section>

            <section className="card">
                <h2 className="section-title">{t('availability.rules')}</h2>
                <div className="form-grid">
                    {numberField('bufferMinutes', 120)}
                    {numberField('minNoticeHours', 168)}
                    {numberField('bookingWindowDays', 365)}
                    {numberField('clientCancelHours', 168)}
                    <Field label={t('availability.reminderHours')} error={errors.reminderHours ?? errors['reminderHours[0]'] ?? errors['reminderHours[1]'] ?? errors['reminderHours[2]']} hint={t('availability.reminderHoursHint')}>
                        <input value={reminders} onChange={(event) => setReminders(event.target.value)} inputMode="numeric" />
                    </Field>
                    <Field label={t('availability.meetingLink')} error={errors.meetingLink} optional hint={t('availability.meetingLinkHint')}>
                        <input type="url" value={meetingLink} maxLength={500} placeholder="https://meet.google.com/…" onChange={(event) => setMeetingLink(event.target.value)} />
                    </Field>
                </div>
            </section>

            <div className="form-actions">
                <Button type="submit" busy={submit.busy}>
                    {t('common.save')}
                </Button>
            </div>
        </form>
    );
}

function TimeInput({ label, value, error, onChange }: { label: string; value: string; error?: string; onChange: (value: string) => void }) {
    return (
        <Field label={label} error={error}>
            <input type="time" step={900} value={value} onChange={(event) => onChange(event.target.value)} />
        </Field>
    );
}
