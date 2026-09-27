import type { Schema } from './types';

export type Enrollment = Schema<'EnrollmentOutput'>;
export type Payment = Schema<'PaymentOutput'>;

export const PAYMENT_STATUSES = ['approved', 'pending', 'declined', 'voided', 'error'] as const;
export const ENROLLMENT_STATUSES = ['pending_payment', 'active', 'completed', 'cancelled'] as const;
export const MANUAL_METHODS = ['cash', 'transfer', 'other'] as const;

/**
 * What can be done to a plan a person has, by whom: pay it (the owner records money), send or copy its link while it
 * waits for payment, cancel it then, and renew or finish it once used up.
 */
export function enrollmentActions(enrollment: Pick<Enrollment, 'status' | 'free' | 'outcome' | 'paymentUrl'>, isOwner: boolean) {
    const waiting = enrollment.status === 'pending_payment' && !enrollment.free;
    const concluding = enrollment.status === 'completed' && !enrollment.outcome;
    return {
        recordPayment: waiting && isOwner,
        copyLink: waiting && !!enrollment.paymentUrl,
        sendLink: waiting && !!enrollment.paymentUrl,
        cancel: enrollment.status === 'pending_payment',
        renew: concluding,
        finish: concluding,
    };
}

/** "1 de 2": sessions used out of those included; booked ones not yet held count as pending. */
export function sessionsLeft(enrollment: Pick<Enrollment, 'sessionsIncluded' | 'sessionsTaken'>): number {
    return Math.max(0, enrollment.sessionsIncluded - enrollment.sessionsTaken);
}

/** The i18n key of a payment method: Wompi's (CARD, PSE, NEQUI, …) or a manual one. */
export function methodKey(method: string | null | undefined): string {
    if (!method) return 'payments.method.none';
    const known = ['CARD', 'PSE', 'NEQUI', 'BANCOLOMBIA_TRANSFER', 'BANCOLOMBIA_QR', 'DAVIPLATA', 'cash', 'transfer', 'other'];
    return known.includes(method) ? `payments.method.${method}` : 'payments.method.unknown';
}

/** Payments of every plan a person has, newest first. */
export function allPayments(enrollments: readonly Enrollment[]): Payment[] {
    return enrollments.flatMap((enrollment) => enrollment.payments).sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}
