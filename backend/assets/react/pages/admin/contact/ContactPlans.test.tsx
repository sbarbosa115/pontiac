import { render, screen, within } from '@testing-library/react';
import React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as auth from '../../../lib/auth';
import type { Enrollment } from '../../../lib/payments';
import type { Schema } from '../../../lib/types';
import ContactPlans from './ContactPlans';

const base = { price: { amount: '250000.00', currency: 'COP' }, free: false, sessionsIncluded: 2, sessionsTaken: 0, sessionsUsed: 0, durationMinutes: 60, outcome: null, sourcePage: null, createdAt: '2026-09-20T15:00:00+00:00', completedAt: null, paymentUrl: null, payments: [] };
const waiting: Enrollment = { ...base, id: 'e1', planName: 'Plan A', status: 'pending_payment', paymentUrl: 'http://localhost/finanzas-claras/pagar/abc' };
const done: Enrollment = { ...base, id: 'e2', planName: 'Diagnóstico', free: true, price: { amount: '0.00', currency: 'COP' }, sessionsIncluded: 1, sessionsTaken: 1, sessionsUsed: 1, status: 'completed' };
const paidAt = '2026-09-21T15:00:00+00:00';
const active: Enrollment = {
    ...base,
    id: 'e3',
    planName: 'Plan B',
    status: 'active',
    payments: [{ id: 'p1', reference: 'PON-ABC', amount: { amount: '250000.00', currency: 'COP' }, status: 'approved', method: 'NEQUI', manual: false, note: null, recordedBy: null, contact: { id: 'c', fullName: 'Laura', email: 'l@demo.test' }, planName: 'Plan B', enrollmentId: 'e3', createdAt: paidAt, paidAt }],
};
const contact = { id: 'c', fullName: 'Laura Gómez', email: 'laura@demo.test', anonymized: false, enrollments: [waiting, done, active] } as unknown as Schema<'ContactDetailOutput'>;

function signedInAs(role: string) {
    vi.spyOn(auth, 'useAuth').mockReturnValue({ roles: [role], me: { account: { features: ['booking', 'payments'] } } } as unknown as auth.Auth);
    vi.spyOn(auth, 'useLocaleSettings').mockReturnValue({ locale: 'es-CO', currency: 'COP', country: 'CO', timezone: 'America/Bogota' });
}

// The first table lists the plans (the payment history below repeats their names).
const actionsOf = (planName: string) =>
    within(screen.getAllByText(planName)[0]?.closest('tr') as HTMLElement)
        .queryAllByRole('button')
        .map((button) => button.textContent);

afterEach(() => vi.restoreAllMocks());

describe('ContactPlans', () => {
    it('lets the owner record money and everyone handle each plan by its state', () => {
        signedInAs(auth.ROLE_OWNER);
        render(<ContactPlans contact={contact} onChanged={() => undefined} />);

        expect(actionsOf('Plan A')).toEqual(['Registrar pago', 'Copiar enlace', 'Reenviar enlace', 'Cancelar']);
        expect(actionsOf('Diagnóstico')).toEqual(['Renovar', 'Finalizar asesoría']);
        expect(actionsOf('Plan B')).toEqual([]);
        expect(screen.getByText('Plan A').closest('tr')).toHaveAttribute('title', 'Esperando pago');
        // The payment history, with Wompi's method in words.
        expect(screen.getByText('PON-ABC').closest('tr')).toHaveTextContent('Nequi');
    });

    it('never offers the assistant to record a payment', () => {
        signedInAs(auth.ROLE_ASSISTANT);
        render(<ContactPlans contact={contact} onChanged={() => undefined} />);

        expect(actionsOf('Plan A')).toEqual(['Copiar enlace', 'Reenviar enlace', 'Cancelar']);
    });
});
