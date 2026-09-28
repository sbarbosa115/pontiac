import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React, { useState } from 'react';
import { describe, expect, it } from 'vitest';
import type { Catalog, PageContent } from '../../../lib/pages';
import SectionsEditor from './SectionsEditor';

const text = (name: string, max: number, required = false) => ({ name, kind: 'text', required, max, maxItems: null, fields: [] });
const catalog = {
    templates: [],
    accents: [],
    fieldTypes: [],
    maxExtraFields: 10,
    sectionTypes: [
        {
            type: 'hero',
            fields: [
                text('eyebrow', 60),
                text('heading', 120, true),
                { name: 'trustPoints', kind: 'items', required: false, max: null, maxItems: 2, fields: [{ name: 'text', kind: 'text', required: true, max: 40 }] },
            ],
        },
        { type: 'cta', fields: [text('heading', 120, true), text('buttonLabel', 40, true)] },
    ],
} as unknown as Catalog;

function Harness() {
    const [content, setContent] = useState<PageContent>({
        sections: [
            { id: 'portada', type: 'hero', enabled: true, fields: { eyebrow: '', heading: 'Hola', trustPoints: [] } },
            { id: 'llamado', type: 'cta', enabled: false, fields: { heading: 'Da el primer paso', buttonLabel: 'Quiero' } },
        ],
        form: { fields: [] },
        seo: { title: '', description: '', imageId: null, index: true },
        settings: { defaultCategoryId: null, accent: 'navy' },
    });
    return (
        <>
            <SectionsEditor catalog={catalog} content={content} onChange={setContent} errors={{}} />
            <output data-testid="sections">{JSON.stringify(content.sections)}</output>
        </>
    );
}

const sections = () => JSON.parse(screen.getByTestId('sections').textContent ?? '[]') as PageContent['sections'];

describe('SectionsEditor', () => {
    it('edits the hero eyebrow and trust points, up to their limit, with their help', async () => {
        render(<Harness />);

        expect(screen.getByText(/frase corta que presenta la página/)).toBeInTheDocument();
        expect(screen.getByText(/quitan dudas/)).toBeInTheDocument();
        await userEvent.type(screen.getByLabelText(/Etiqueta sobre el título/), 'Diagnóstico gratuito');
        const add = screen.getByRole('button', { name: 'Agregar' });
        await userEvent.click(add);
        await userEvent.type(screen.getByLabelText(/^Texto/), 'Sin costo');
        await userEvent.click(add);

        expect(add).toBeDisabled();
        expect(sections()[0]?.fields).toMatchObject({ eyebrow: 'Diagnóstico gratuito', trustPoints: [{ text: 'Sin costo' }, { text: '' }] });
    });

    it('turns the call-to-action band on and moves it above the hero', async () => {
        render(<Harness />);
        const band = screen.getByText('Llamado a la acción').closest('.section-card') as HTMLElement;

        await userEvent.click(within(band).getByLabelText('Visible'));
        await userEvent.click(within(band).getByRole('button', { name: 'Subir' }));

        expect(sections().map((section) => [section.id, section.enabled])).toEqual([
            ['llamado', true],
            ['portada', true],
        ]);
    });
});
