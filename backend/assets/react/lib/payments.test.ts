import { describe, expect, it } from 'vitest';
import { allPayments, type Enrollment, enrollmentActions, methodKey, sessionsLeft } from './payments';

const link = 'http://localhost/finanzas-claras/pagar/abc';

describe('payments', () => {
    it('offers on each plan only what can happen to it, and money to the owner only', () => {
        const waiting = { status: 'pending_payment', free: false, outcome: null, paymentUrl: link };
        expect(enrollmentActions(waiting, true)).toEqual({ recordPayment: true, copyLink: true, sendLink: true, cancel: true, renew: false, finish: false });
        expect(enrollmentActions(waiting, false).recordPayment).toBe(false);
        expect(enrollmentActions({ status: 'active', free: false, outcome: null, paymentUrl: null }, true)).toEqual({ recordPayment: false, copyLink: false, sendLink: false, cancel: false, renew: false, finish: false });
        expect(enrollmentActions({ status: 'completed', free: true, outcome: null, paymentUrl: null }, false)).toMatchObject({ renew: true, finish: true });
        expect(enrollmentActions({ status: 'completed', free: true, outcome: 'renewed', paymentUrl: null }, true)).toMatchObject({ renew: false, finish: false });
    });

    it('counts the sessions left and names the methods', () => {
        expect(sessionsLeft({ sessionsIncluded: 2, sessionsTaken: 1 })).toBe(1);
        expect(sessionsLeft({ sessionsIncluded: 2, sessionsTaken: 3 })).toBe(0);
        expect(methodKey('NEQUI')).toBe('payments.method.NEQUI');
        expect(methodKey('SOMETHING_NEW')).toBe('payments.method.unknown');
        expect(methodKey(null)).toBe('payments.method.none');
    });

    it('lists the payments of every plan, newest first', () => {
        const payment = (id: string, createdAt: string) => ({ id, createdAt }) as Enrollment['payments'][number];
        const enrollments = [{ payments: [payment('b', '2026-09-02T10:00:00+00:00')] }, { payments: [payment('a', '2026-09-01T10:00:00+00:00'), payment('c', '2026-09-03T10:00:00+00:00')] }] as Enrollment[];
        expect(allPayments(enrollments).map((p) => p.id)).toEqual(['c', 'b', 'a']);
    });
});
