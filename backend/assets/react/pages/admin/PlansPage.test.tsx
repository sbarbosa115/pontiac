import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as auth from '../../lib/auth';
import { api } from '../../lib/api';
import type { Schema } from '../../lib/types';
import PlansPage from './PlansPage';

type Plan = Schema<'PlanOutput'>;

const free: Plan = { id: '1', name: 'Diagnóstico', description: 'Primera sesión', price: { amount: '0.00', currency: 'COP' }, free: true, sessions: 1, durationMinutes: 45, active: true };
const paid: Plan = { id: '2', name: 'Plan A', description: '', price: { amount: '250000.00', currency: 'COP' }, free: false, sessions: 2, durationMinutes: 60, active: true };

function signedInAs(role: string) {
    vi.spyOn(auth, 'useAuth').mockReturnValue({ roles: [role] } as unknown as auth.Auth);
    vi.spyOn(auth, 'useLocaleSettings').mockReturnValue({ locale: 'es-CO', currency: 'COP', country: 'CO', timezone: 'America/Bogota' });
    vi.spyOn(api, 'get').mockResolvedValue({ items: [free, paid], total: 2, page: 1, perPage: 25 });
}

afterEach(() => vi.restoreAllMocks());

describe('PlansPage', () => {
    it('shows prices in pesos, a free plan as such, and lets the owner edit', async () => {
        signedInAs(auth.ROLE_OWNER);
        render(<PlansPage />);

        expect(await screen.findByText('Plan A')).toBeInTheDocument();
        expect(screen.getByText('Gratuito')).toBeInTheDocument();
        expect(screen.getByText(/250\.000/)).toBeInTheDocument();
        expect(screen.getByText('45 minutos')).toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: 'Editar' })).toHaveLength(2);

        await userEvent.click(screen.getAllByRole('button', { name: 'Editar' })[1] as HTMLElement);
        const dialog = screen.getByRole('dialog', { name: 'Editar plan' });
        expect(dialog).toBeInTheDocument();
        expect(screen.getByDisplayValue('250.000')).toBeInTheDocument();
    });

    it('shows an assistant the plans with nothing to change', async () => {
        signedInAs(auth.ROLE_ASSISTANT);
        render(<PlansPage />);

        expect(await screen.findByText('Plan A')).toBeInTheDocument();
        expect(screen.queryByRole('columnheader', { name: 'Acciones' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Nuevo plan' })).not.toBeInTheDocument();
    });
});
