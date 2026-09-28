import { newStageId, problemsOf, saveBody, toEditor, type Flow } from './flows';

const flow: Flow = {
    id: 'f1',
    name: 'Diagnóstico',
    active: true,
    pages: [],
    stages: [
        { id: 's1', name: 'Nuevo', kind: 'start', x: 0, y: 0, emailTemplateId: null, alertDays: null, people: 2 },
        { id: 's2', name: 'Sesión agendada', kind: 'step', x: 280.4, y: 120.6, people: 0 },
    ],
    transitions: [{ from: 's1', to: 's2', trigger: 'session_booked' }],
};

describe('flows', () => {
    it('saves the canvas it was given, in whole pixels', () => {
        const { stages, arrows } = toEditor(flow);

        expect(saveBody('Diagnóstico', stages, arrows)).toEqual({
            name: 'Diagnóstico',
            stages: [
                { id: 's1', name: 'Nuevo', kind: 'start', x: 0, y: 0, emailTemplateId: null, alertDays: null },
                { id: 's2', name: 'Sesión agendada', kind: 'step', x: 280, y: 121, emailTemplateId: null, alertDays: null },
            ],
            transitions: [{ from: 's1', to: 's2', trigger: 'session_booked' }],
        });
    });

    it('names new stages apart from the ones it has', () => {
        expect(newStageId([{ id: 's1' }, { id: 'new-3' }])).toBe('new-4');
        expect(newStageId([{ id: 'new-2' }, { id: 'new-3' }])).toBe('new-4');
    });

    it('puts each problem on the stage or arrow it is about', () => {
        const { stages, arrows } = toEditor(flow);

        expect(
            problemsOf(
                { 'stages[1].name': 'Ponle nombre.', 'transitions[0].trigger': 'Elige qué mueve.', stages: 'Una sola etapa de inicio.', 'stages[9].name': 'Fuera.' },
                stages,
                arrows,
            ),
        ).toEqual({
            general: ['Una sola etapa de inicio.', 'Fuera.'],
            stages: { s2: ['Ponle nombre.'] },
            arrows: { [arrows[0]!.id]: ['Elige qué mueve.'] },
        });
    });
});
