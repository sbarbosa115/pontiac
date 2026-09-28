import type { Schema } from './types';

export type Session = Schema<'SessionOutput'>;
export type WeeklyRule = Schema<'WeeklyRuleOutput'>;

export const SESSION_STATUSES = ['scheduled', 'done', 'no_show', 'cancelled'] as const;

/** 1 (Monday) … 7 (Sunday), as the API numbers weekdays. */
export const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7] as const;

/** The calendar date (YYYY-MM-DD) an instant falls on in a timezone. */
export function localDate(iso: string, timeZone: string): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(iso));
}

/** "2026-10-07" + 3 → "2026-10-10": calendar arithmetic, no timezone involved. */
export function addDays(date: string, days: number): string {
    const at = new Date(`${date}T00:00:00Z`);
    at.setUTCDate(at.getUTCDate() + days);
    return at.toISOString().slice(0, 10);
}

/** The Monday of the week a date is in. */
export function mondayOf(date: string): string {
    const weekday = new Date(`${date}T00:00:00Z`).getUTCDay() || 7;
    return addDays(date, 1 - weekday);
}

/** The seven dates of the week starting on `monday`, each with its sessions (soonest first) in the account's timezone. */
export function weekDays(monday: string, sessions: readonly Session[], timeZone: string): { date: string; sessions: Session[] }[] {
    return WEEKDAYS.map((weekday) => {
        const date = addDays(monday, weekday - 1);
        return {
            date,
            sessions: sessions.filter((session) => localDate(session.startsAt, timeZone) === date).sort((a, b) => a.startsAt.localeCompare(b.startsAt)),
        };
    });
}

/** "10:00 a. m." in the account's locale and timezone. */
export function formatTime(iso: string, locale: string, timeZone: string): string {
    return new Intl.DateTimeFormat(locale, { timeStyle: 'short', timeZone }).format(new Date(iso));
}

/** "lunes 5 oct." — a column header of the week. */
export function formatDay(date: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'short', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
}

/** The weekday's name ("lunes") for the API's 1–7. 2024-01-01 was a Monday. */
export function weekdayName(weekday: number, locale: string): string {
    return new Intl.DateTimeFormat(locale, { weekday: 'long', timeZone: 'UTC' }).format(new Date(Date.UTC(2024, 0, weekday)));
}

/** Which actions a session offers: move and cancel while scheduled; done / no-show once it started; reopen once closed. */
export function sessionActions(session: Pick<Session, 'status' | 'startsAt'>, now: Date = new Date()) {
    const scheduled = session.status === 'scheduled';
    const started = new Date(session.startsAt) <= now;
    return {
        reschedule: scheduled,
        cancel: scheduled,
        close: scheduled && started,
        reopen: session.status === 'done' || session.status === 'no_show',
    };
}

/** "24, 1" → [24, 1]; anything that is not a whole number is dropped (the API says what is missing). */
export function parseHours(text: string): number[] {
    return text
        .split(/[\s,;]+/)
        .filter(Boolean)
        .map(Number)
        .filter((value) => Number.isInteger(value));
}
