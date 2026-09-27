import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../lib/api';
import { CreateConsultant, suggestSlug } from './AccountsPage';

afterEach(() => vi.restoreAllMocks());

describe('suggestSlug', () => {
    it('turns a practice name into an address', () => {
        expect(suggestSlug('Finanzas Claras SAS')).toBe('finanzas-claras-sas');
        expect(suggestSlug('  Asesorías Ñuñez & Cía. ')).toBe('asesorias-nunez-cia');
        expect(suggestSlug('!!!')).toBe('');
    });
});

describe('CreateConsultant', () => {
    it('suggests the address from the name until the super admin types their own', async () => {
        render(<CreateConsultant onClose={() => {}} onCreated={() => {}} />);
        const name = screen.getByLabelText('Nombre de la práctica');
        const slug = screen.getByLabelText(/^Dirección/);

        await userEvent.type(name, 'Plata Sana');
        expect(slug).toHaveValue('plata-sana');

        await userEvent.clear(slug);
        await userEvent.type(slug, 'Plata');
        await userEvent.type(name, ' SAS');
        expect(slug).toHaveValue('plata');
    });

    it('shows the API\'s reasons under each field', async () => {
        vi.spyOn(api, 'post').mockRejectedValue(
            Object.assign(new (await import('../../lib/api')).ApiError(422, {
                error: 'validation_failed',
                violations: [{ field: 'slug', message: 'Esa dirección ya la usa otro asesor.' }],
            })),
        );
        render(<CreateConsultant onClose={() => {}} onCreated={() => {}} />);

        await userEvent.type(screen.getByLabelText('Nombre de la práctica'), 'Finanzas Claras');
        await userEvent.click(screen.getByRole('button', { name: 'Crear e invitar' }));

        expect(await screen.findByText('Esa dirección ya la usa otro asesor.')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/api/platform/accounts', { name: 'Finanzas Claras', slug: 'finanzas-claras', ownerName: '', ownerEmail: '' });
    });
});
