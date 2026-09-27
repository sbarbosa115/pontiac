import { render, screen } from '@testing-library/react';
import React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as auth from '../../../lib/auth';
import { api } from '../../../lib/api';
import { messages } from '../../../lib/i18n';
import { TRIGGERS } from '../../../lib/flows';
import ContactHistory from './ContactHistory';

afterEach(() => vi.restoreAllMocks());

describe('ContactHistory', () => {
    it('says what moved the person and which emails they got, coloured by kind', async () => {
        vi.spyOn(auth, 'useLocaleSettings').mockReturnValue({ locale: 'es-CO', currency: 'COP', country: 'CO', timezone: 'America/Bogota' });
        vi.spyOn(api, 'get').mockResolvedValue({
            items: [
                { type: 'email', at: '2026-09-27T15:00:01Z', reason: 'flow_stage', subject: 'Nos vemos pronto', emailStatus: 'failed' },
                { type: 'flow', at: '2026-09-27T15:00:00Z', flowName: 'Diagnóstico', fromStage: 'Nuevo', toStage: 'Sesión agendada', reason: 'session_booked', subject: 'Nos vemos pronto' },
                { type: 'flow', at: '2026-09-26T15:00:00Z', flowName: 'Diagnóstico', fromStage: null, toStage: 'Nuevo', reason: 'added', by: 'Andrés Asesor' },
            ],
        });
        render(<ContactHistory contactId="c1" />);

        expect(await screen.findByText('«Nuevo» → «Sesión agendada»')).toBeInTheDocument();
        expect(screen.getByText('Evento: Reservó una sesión')).toBeInTheDocument();
        expect(screen.getByText('Entró a «Nuevo»')).toBeInTheDocument();
        expect(screen.getByText('Por Andrés Asesor')).toBeInTheDocument();
        expect(screen.getByText('Correo de un flujo').closest('tr')).toHaveClass('row-tone-danger');
        expect(screen.getByText('Entró a «Nuevo»').closest('tr')).toHaveClass('row-tone-accent');
    });

    it('has a name for every event a flow knows', () => {
        for (const trigger of TRIGGERS) expect(messages).toHaveProperty([`flows.trigger.${trigger}`]);
        for (const kind of ['start', 'step', 'end']) expect(messages).toHaveProperty([`flows.kind.${kind}`]);
    });
});
