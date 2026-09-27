import { render, screen, within } from '@testing-library/react';
import React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as auth from '../../lib/auth';
import { api } from '../../lib/api';
import type { PortalPlan, PortalSession } from '../../lib/portal';
import SessionsPage from './SessionsPage';

const base = { endsAt: '2999-01-04T16:00:00+00:00', planName: 'Plan A', durationMinutes: 60, meetingLink: 'https://meet.example/x', cancelReason: null };
const changeable: PortalSession = { ...base, id: 's1', startsAt: '2999-01-04T15:00:00+00:00', status: 'scheduled', canChange: true };
const tooLate: PortalSession = { ...base, id: 's2', startsAt: '2999-01-03T15:00:00+00:00', endsAt: '2999-01-03T16:00:00+00:00', status: 'scheduled', canChange: false, planName: 'Plan B' };
const done: PortalSession = { ...base, id: 's3', startsAt: '2020-01-03T15:00:00+00:00', endsAt: '2020-01-03T16:00:00+00:00', status: 'done', canChange: false, planName: 'Diagnóstico' };
const plan = (sessionsTaken: number) => ({ id: 'p1', planName: 'Plan A', status: 'active', sessionsIncluded: 2, sessionsTaken, sessionsUsed: 1, durationMinutes: 60, payments: [] }) as unknown as PortalPlan;

function withData(taken: number) {
    vi.spyOn(auth, 'useLocaleSettings').mockReturnValue({ locale: 'es-CO', currency: 'COP', country: 'CO', timezone: 'America/Bogota' });
    vi.spyOn(api, 'get').mockImplementation((path: string) => Promise.resolve(path.endsWith('/sessions') ? { items: [changeable, tooLate, done] } : { items: [plan(taken)] }) as never);
}

afterEach(() => vi.restoreAllMocks());

describe('portal SessionsPage', () => {
    it('lets the client change a session only before the limit, and join while it is ahead', async () => {
        withData(1);
        render(<SessionsPage />);

        const row = async (text: string) => within((await screen.findByText(text)).closest('tr') as HTMLElement);
        expect((await row('Plan A')).getAllByRole('button').map((b) => b.textContent)).toEqual(['Reprogramar', 'Cancelar']);
        expect((await row('Plan A')).getByRole('link', { name: 'Entrar a la sesión' })).toBeInTheDocument();
        expect((await row('Plan B')).queryAllByRole('button')).toHaveLength(0);
        expect((await row('Diagnóstico')).queryByRole('link')).not.toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: 'Agendar sesión' }).length).toBeGreaterThan(0);
    });

    it('offers no booking when every session of their plans is taken', async () => {
        withData(2);
        render(<SessionsPage />);

        expect(await screen.findByText('Para agendar necesitas un plan activo con sesiones disponibles.')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Agendar sesión' })).not.toBeInTheDocument();
    });
});
