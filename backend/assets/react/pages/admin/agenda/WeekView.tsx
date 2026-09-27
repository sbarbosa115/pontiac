import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../../lib/api';
import { addDays, formatDay, formatTime, mondayOf, weekDays } from '../../../lib/agenda';
import { useLocaleSettings } from '../../../lib/auth';
import { todayIn } from '../../../lib/format';
import { useApi } from '../../../lib/hooks';
import { t } from '../../../lib/i18n';
import type { Get } from '../../../lib/types';
import { useSessionActions } from '../../../components/SessionModals';
import { Alert, Button, ErrorState, Loading, TabIntro } from '../../../components/ui';

/** Agenda › Semana: the scheduled sessions of one week, a column per day, in the consultant's timezone. */
export default function WeekView({ onBook }: { onBook: () => void }) {
    const { locale, timezone } = useLocaleSettings();
    const today = todayIn(timezone);
    const [monday, setMonday] = useState(() => mondayOf(today));
    const week = useApi(() => api.get<Get<'/api/admin/sessions/week'>>('/api/admin/sessions/week', { start: monday }), [monday]);
    const actions = useSessionActions(() => week.reload());

    if (week.error) return <ErrorState error={week.error} onRetry={week.reload} />;

    const sunday = addDays(monday, 6);
    const days = weekDays(monday, week.data?.items ?? [], timezone);
    const empty = week.data && week.data.items.length === 0;

    return (
        <>
            <TabIntro
                action={
                    <div className="week-nav">
                        <Button variant="ghost" size="sm" onClick={() => setMonday(addDays(monday, -7))}>
                            ← {t('agenda.previousWeek')}
                        </Button>
                        <Button variant="ghost" size="sm" disabled={monday === mondayOf(today)} onClick={() => setMonday(mondayOf(today))}>
                            {t('agenda.thisWeek')}
                        </Button>
                        <Button variant="ghost" size="sm" onClick={() => setMonday(addDays(monday, 7))}>
                            {t('agenda.nextWeek')} →
                        </Button>
                        <Button onClick={onBook}>{t('agenda.book')}</Button>
                    </div>
                }
            >
                {t('agenda.weekOf', { from: formatDay(monday, locale), to: formatDay(sunday, locale) })}
            </TabIntro>
            <Alert kind="error" onDismiss={actions.clearError}>
                {actions.error}
            </Alert>
            {!week.data ? (
                <Loading />
            ) : (
                <>
                    {empty && <p className="muted small">{t('agenda.weekEmpty')}</p>}
                    <div className={`week-grid${week.loading ? ' is-reloading' : ''}`}>
                        {days.map((day) => (
                            <section key={day.date} className={`week-day${day.date === today ? ' is-today' : ''}`} aria-label={formatDay(day.date, locale)}>
                                <h3 className="week-day-title">{formatDay(day.date, locale)}</h3>
                                {day.sessions.length === 0 && <p className="muted small">{t('agenda.dayFree')}</p>}
                                {day.sessions.map((session) => (
                                    <article key={session.id} className="week-session">
                                        <p className="strong">
                                            {formatTime(session.startsAt, locale, timezone)} – {formatTime(session.endsAt, locale, timezone)}
                                        </p>
                                        <p>
                                            <Link to={`/admin/prospectos/${session.contact.id}`}>{session.contact.fullName}</Link>
                                        </p>
                                        <p className="small muted">{session.planName}</p>
                                        <div className="row-actions">{actions.buttons(session)}</div>
                                    </article>
                                ))}
                            </section>
                        ))}
                    </div>
                </>
            )}
            {actions.modals}
        </>
    );
}
