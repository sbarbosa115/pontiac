import { describe, expect, it } from 'vitest';
import { emptyItem, errorsUnder, move, type PageContent, setSectionField } from './pages';

const content: PageContent = {
    sections: [
        { id: 'portada', type: 'hero', enabled: true, fields: { heading: 'Hola' } },
        { id: 'preguntas', type: 'faq', enabled: true, fields: { heading: 'FAQ', items: [] } },
    ],
    form: { fields: [] },
    seo: { title: 'T', description: '', imageId: null, index: true },
    settings: { defaultCategoryId: null, accent: 'navy' },
};

describe('page content helpers', () => {
    it('moves an item and leaves the list alone when the move goes nowhere', () => {
        expect(move(['a', 'b', 'c'], 0, 2)).toEqual(['b', 'c', 'a']);
        expect(move(['a', 'b', 'c'], 0, -1)).toEqual(['a', 'b', 'c']);
        expect(move(['a', 'b', 'c'], 2, 3)).toEqual(['a', 'b', 'c']);
    });

    it('changes one field of one section without touching the rest', () => {
        const next = setSectionField(content, 0, 'heading', 'Adiós');
        expect(next.sections[0]?.fields.heading).toBe('Adiós');
        expect(next.sections[1]).toBe(content.sections[1]);
        expect(content.sections[0]?.fields.heading).toBe('Hola');
    });

    it('makes an empty item with every field of the spec', () => {
        expect(
            emptyItem({
                name: 'items',
                kind: 'items',
                required: false,
                max: null,
                maxItems: 8,
                fields: [
                    { name: 'question', kind: 'text', required: true, max: 200 },
                    { name: 'answer', kind: 'textarea', required: true, max: 1000 },
                ],
            }),
        ).toEqual({ question: '', answer: '' });
    });

    it('picks the errors of one part of the editor', () => {
        const errors = { 'sections[1].fields.items[0].question': 'x', 'sections[10].fields.heading': 'y', 'seo.title': 'z' };
        expect(Object.keys(errorsUnder(errors, 'sections[1]'))).toEqual(['sections[1].fields.items[0].question']);
    });
});
