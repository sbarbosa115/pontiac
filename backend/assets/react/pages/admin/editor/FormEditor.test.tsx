import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React, { useState } from 'react';
import { describe, expect, it } from 'vitest';
import type { Catalog, PageContent } from '../../../lib/pages';
import FormEditor from './FormEditor';

const catalog = { templates: [], sectionTypes: [], accents: [], fieldTypes: ['text', 'textarea', 'select', 'checkbox', 'number'], maxExtraFields: 2 } as Catalog;
const categories = [{ id: 'c1', name: 'Deudas', color: 'rose', active: true }];

function Harness({ errors = {} }: { errors?: Record<string, string> }) {
    const [content, setContent] = useState<PageContent>({
        sections: [],
        form: { fields: [] },
        seo: { title: '', description: '', imageId: null, index: true },
        settings: { defaultCategoryId: null, accent: 'navy' },
    });
    return (
        <>
            <FormEditor catalog={catalog} content={content} categories={categories} onChange={setContent} errors={errors} />
            <output data-testid="fields">{JSON.stringify(content.form.fields)}</output>
        </>
    );
}

describe('FormEditor', () => {
    it('adds a list question whose answers sort people into categories', async () => {
        render(<Harness />);

        await userEvent.click(screen.getByRole('button', { name: 'Agregar pregunta' }));
        await userEvent.type(screen.getByLabelText('Pregunta'), '¿Qué te preocupa?');
        await userEvent.selectOptions(screen.getByLabelText('Tipo de respuesta'), 'select');
        await userEvent.type(screen.getByLabelText(/^Opciones/), 'Mis deudas{enter}Otra cosa');
        await userEvent.selectOptions(screen.getByLabelText('Mis deudas'), 'c1');
        await userEvent.click(screen.getByLabelText('Obligatoria'));

        expect(JSON.parse(screen.getByTestId('fields').textContent ?? '[]')).toEqual([
            { label: '¿Qué te preocupa?', type: 'select', required: true, options: ['Mis deudas', 'Otra cosa'], optionCategories: { 'Mis deudas': 'c1' } },
        ]);
    });

    it('stops adding questions at the limit', async () => {
        render(<Harness />);
        const add = screen.getByRole('button', { name: 'Agregar pregunta' });

        await userEvent.click(add);
        await userEvent.click(add);

        expect(add).toBeDisabled();
    });

    it('shows the API\'s reason under the question it is about', () => {
        render(<Harness errors={{ 'form.fields': 'Máximo 10 campos adicionales.' }} />);

        expect(screen.getByText('Máximo 10 campos adicionales.')).toBeInTheDocument();
    });
});
