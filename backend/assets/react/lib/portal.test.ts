import { describe, expect, it } from 'vitest';
import { bookablePlans, type PortalPlan, type PortalSession, splitSessions } from './portal';

const plan = (id: string, status: string, taken: number, included = 2) => ({ id, status, sessionsTaken: taken, sessionsIncluded: included }) as PortalPlan;
const session = (id: string, startsAt: string, status = 'scheduled') => ({ id, startsAt, endsAt: startsAt, status }) as PortalSession;

describe('portal', () => {
    it('offers booking only on active plans with sessions left', () => {
        expect(bookablePlans([plan('a', 'active', 1), plan('b', 'active', 2), plan('c', 'pending_payment', 0), plan('d', 'completed', 2)]).map((p) => p.id)).toEqual(['a']);
    });

    it('puts what is ahead first, soonest first, and the rest after', () => {
        const now = new Date('2026-10-01T12:00:00Z');
        const { upcoming, past } = splitSessions(
            [session('later', '2026-10-09T15:00:00+00:00'), session('done', '2026-09-20T15:00:00+00:00', 'done'), session('soon', '2026-10-02T15:00:00+00:00'), session('gone', '2026-09-30T15:00:00+00:00')],
            now,
        );
        expect(upcoming.map((s) => s.id)).toEqual(['soon', 'later']);
        expect(past.map((s) => s.id)).toEqual(['gone', 'done']);
    });
});
