import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as auth from '../../lib/auth';
import { api } from '../../lib/api';
import type { Schema } from '../../lib/types';
import TeamPage from './TeamPage';

type Member = Schema<'TeamMemberOutput'>;

const owner: Member = { id: '1', email: 'asesor@demo.test', fullName: 'Andrés Asesor', role: 'owner', active: true, loginStatus: 'active', lastSignInAt: null };
const invited: Member = { id: '2', email: 'sofia@demo.test', fullName: 'Sofía Asistente', role: 'assistant', active: true, loginStatus: 'invited', lastSignInAt: null };
const disabled: Member = { id: '3', email: 'beto@demo.test', fullName: 'Beto Bravo', role: 'assistant', active: false, loginStatus: 'active', lastSignInAt: null };

function signedInAs(role: string) {
    vi.spyOn(auth, 'useAuth').mockReturnValue({ roles: [role] } as unknown as auth.Auth);
    vi.spyOn(auth, 'useLocaleSettings').mockReturnValue({ locale: 'es-CO', currency: 'COP', country: 'CO', timezone: 'America/Bogota' });
    vi.spyOn(api, 'get').mockResolvedValue({ items: [owner, invited, disabled], total: 3, page: 1, perPage: 25 });
}

afterEach(() => vi.restoreAllMocks());

describe('TeamPage', () => {
    it('lets the consultant invite, resend an invitation, and turn assistants off and on — never themselves', async () => {
        signedInAs(auth.ROLE_OWNER);
        render(<TeamPage />);

        expect(await screen.findByText('Sofía Asistente')).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Acciones' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Reenviar invitación' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Desactivar' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Activar' })).toBeInTheDocument();
        // One row each for Sofía and Beto: the consultant's own row has no switch.
        expect(screen.getAllByRole('button', { name: /^(Desactivar|Activar)$/ })).toHaveLength(2);

        await userEvent.click(screen.getByRole('button', { name: 'Invitar asistente' }));
        expect(screen.getByRole('dialog', { name: 'Invitar asistente' })).toBeInTheDocument();
    });

    it('shows an assistant the team with nothing to change', async () => {
        signedInAs(auth.ROLE_ASSISTANT);
        render(<TeamPage />);

        expect(await screen.findByText('Sofía Asistente')).toBeInTheDocument();
        expect(screen.queryByRole('columnheader', { name: 'Acciones' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Invitar asistente' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Desactivar' })).not.toBeInTheDocument();
    });
});
