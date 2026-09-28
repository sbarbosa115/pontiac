import { describe, expect, it } from 'vitest';
import { addDays, localDate, mondayOf, parseHours, type Session, sessionActions, weekDays, weekdayName } from './agenda';

const session = (id: string, startsAt: string): Session =>
    ({ id, startsAt, endsAt: startsAt, status: 'scheduled', contact: { id: 'c', fullName: 'Laura', email: 'l@demo.test' }, planName: 'Diagnóstico', durationMinutes: 45, meetingLink: '', cancelReason: null, bookedBy: 'visitor' }) as Session;

describe('agenda', () => {
    it('places a session on the day it falls in Bogotá, not in UTC', () => {
        // 02:00 UTC on Tuesday is still Monday 21:00 in Bogotá.
        expect(localDate('2026-10-06T02:00:00+00:00', 'America/Bogota')).toBe('2026-10-05');
        const days = weekDays('2026-10-05', [session('late', '2026-10-06T02:00:00+00:00'), session('early', '2026-10-05T14:00:00+00:00')], 'America/Bogota');
        expect(days).toHaveLength(7);
        expect(days[0]?.sessions.map((s) => s.id)).toEqual(['early', 'late']);
        expect(days[1]?.sessions).toEqual([]);
    });

    it('finds the Monday of any day and walks weeks across months', () => {
        expect(mondayOf('2026-10-11')).toBe('2026-10-05');
        expect(mondayOf('2026-10-05')).toBe('2026-10-05');
        expect(addDays('2026-10-26', 7)).toBe('2026-11-02');
        expect(weekdayName(1, 'es-CO')).toBe('lunes');
        expect(weekdayName(7, 'es-CO')).toBe('domingo');
    });

    it('offers each action only when it makes sense', () => {
        const now = new Date('2026-10-05T15:00:00Z');
        expect(sessionActions({ status: 'scheduled', startsAt: '2026-10-06T15:00:00+00:00' }, now)).toEqual({ reschedule: true, cancel: true, close: false, reopen: false });
        expect(sessionActions({ status: 'scheduled', startsAt: '2026-10-05T14:00:00+00:00' }, now)).toMatchObject({ close: true });
        expect(sessionActions({ status: 'done', startsAt: '2026-10-05T14:00:00+00:00' }, now)).toEqual({ reschedule: false, cancel: false, close: false, reopen: true });
        expect(sessionActions({ status: 'cancelled', startsAt: '2026-10-05T14:00:00+00:00' }, now)).toEqual({ reschedule: false, cancel: false, close: false, reopen: false });
    });

    it('reads reminder hours as typed', () => {
        expect(parseHours('24, 1')).toEqual([24, 1]);
        expect(parseHours(' 48 ;2 x ')).toEqual([48, 2]);
    });
});
