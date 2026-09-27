import { render, screen, within } from '@testing-library/react';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as auth from '../../../lib/auth';
import { api } from '../../../lib/api';
import type { Session } from '../../../lib/agenda';
import SessionsList from './SessionsList';

const base: Omit<Session, 'id' | 'startsAt' | 'endsAt' | 'status'> = {
    contact: { id: 'c1', fullName: 'Laura Gómez', email: 'laura@demo.test' },
    planName: 'Diagnóstico',
    durationMinutes: 45,
    meetingLink: 'https://meet.example/abc',
    cancelReason: null,
    bookedBy: 'visitor',
};
const upcoming: Session = { ...base, id: 's1', startsAt: '2999-01-04T15:00:00+00:00', endsAt: '2999-01-04T15:45:00+00:00', status: 'scheduled' };
const happened: Session = { ...base, id: 's2', startsAt: '2020-01-06T15:00:00+00:00', endsAt: '2020-01-06T15:45:00+00:00', status: 'scheduled', contact: { id: 'c2', fullName: 'Carlos Ruiz', email: 'carlos@demo.test' } };
const cancelled: Session = { ...base, id: 's3', startsAt: '2999-01-05T15:00:00+00:00', endsAt: '2999-01-05T15:45:00+00:00', status: 'cancelled', cancelReason: 'Viaje', contact: { id: 'c3', fullName: 'Marta Díaz', email: 'marta@demo.test' } };

afterEach(() => vi.restoreAllMocks());

describe('SessionsList', () => {
    it('offers on each row only what can happen to that session now', async () => {
        vi.spyOn(auth, 'useLocaleSettings').mockReturnValue({ locale: 'es-CO', currency: 'COP', country: 'CO', timezone: 'America/Bogota' });
        const get = vi.spyOn(api, 'get').mockResolvedValue({ items: [upcoming, happened, cancelled], total: 3, page: 1, perPage: 25 });
        render(
            <MemoryRouter>
                <SessionsList onBook={() => undefined} />
            </MemoryRouter>,
        );

        const laura = (await screen.findByText('Laura Gómez')).closest('tr') as HTMLElement;
        expect(within(laura).getByRole('button', { name: 'Reprogramar' })).toBeInTheDocument();
        expect(within(laura).getByRole('button', { name: 'Cancelar sesión' })).toBeInTheDocument();
        expect(within(laura).queryByRole('button', { name: 'Marcar como realizada' })).not.toBeInTheDocument();
        expect(within(laura).getByRole('link', { name: 'Abrir el enlace de la reunión' })).toHaveAttribute('href', 'https://meet.example/abc');

        const carlos = screen.getByText('Carlos Ruiz').closest('tr') as HTMLElement;
        expect(within(carlos).getByRole('button', { name: 'Marcar como realizada' })).toBeInTheDocument();
        expect(within(carlos).getByRole('button', { name: 'Marcar que no asistió' })).toBeInTheDocument();

        const marta = screen.getByText('Marta Díaz').closest('tr') as HTMLElement;
        expect(within(marta).queryAllByRole('button')).toHaveLength(0);
        expect(within(marta).getByText('Viaje')).toBeInTheDocument();
        expect(marta).toHaveAttribute('title', 'Cancelada');

        // Upcoming sessions first: the list starts from today.
        expect(get.mock.calls[0]?.[1]).toMatchObject({ from: expect.stringMatching(/^\d{4}-\d\d-\d\d$/), to: '' });
    });
});
