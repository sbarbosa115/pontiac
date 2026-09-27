import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../lib/api';
import type { Schema } from '../../lib/types';
import FlowBoard from './FlowBoard';

const board: Schema<'BoardOutput'> = {
    flowId: 'f1',
    flowName: 'Diagnóstico',
    columns: [
        { stageId: 's1', name: 'Nuevo', kind: 'start', alertDays: null, cards: [{ contactId: 'c1', fullName: 'Laura Gómez', status: 'lead', category: { id: 'k', name: 'Deudas', color: 'rose', active: true }, enteredAt: '2026-09-20T10:00:00Z', days: 7, overdue: true }] },
        { stageId: 's2', name: 'Sesión agendada', kind: 'step', alertDays: 3, cards: [] },
    ],
};

afterEach(() => vi.restoreAllMocks());

describe('FlowBoard', () => {
    it('shows a column per stage, marks who waits too long, and moves a person from their card', async () => {
        vi.spyOn(api, 'get').mockImplementation(async (path: string) =>
            (path === '/api/admin/flows/all' ? { items: [{ id: 'f1', name: 'Diagnóstico', active: true, stages: 2, people: 1 }] } : board) as never,
        );
        const post = vi.spyOn(api, 'post').mockResolvedValue({ items: [] });
        render(
            <MemoryRouter>
                <FlowBoard embedded />
            </MemoryRouter>,
        );

        const nuevo = await screen.findByRole('listitem', { name: 'Nuevo' });
        expect(within(nuevo).getByText('Laura Gómez')).toBeInTheDocument();
        expect(within(nuevo).getByText(/7 días · lleva más de lo esperado/)).toBeInTheDocument();
        expect(within(screen.getByRole('listitem', { name: 'Sesión agendada' })).getByText('Nadie en esta etapa.')).toBeInTheDocument();
        expect(screen.getByText('Aviso después de 3 días')).toBeInTheDocument();

        await userEvent.selectOptions(screen.getByLabelText('Mover a Laura Gómez a otra etapa'), 's2');

        await waitFor(() => expect(post).toHaveBeenCalledWith('/api/admin/flows/f1/people/c1/move', { stageId: 's2' }));
    });

    it('sends to Flujos when there is no active flow', async () => {
        vi.spyOn(api, 'get').mockResolvedValue({ items: [{ id: 'f1', name: 'Viejo', active: false, stages: 2, people: 0 }] });
        render(
            <MemoryRouter>
                <FlowBoard embedded />
            </MemoryRouter>,
        );

        expect(await screen.findByRole('link', { name: 'Ir a Flujos' })).toHaveAttribute('href', '/admin/flujos');
    });
});
