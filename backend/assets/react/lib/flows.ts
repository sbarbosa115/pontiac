import type { Schema } from './types';

export type Flow = Schema<'FlowOutput'>;
export type FlowStage = Schema<'FlowStageOutput'>;
export type FlowTransition = Schema<'FlowTransitionOutput'>;

/** What moves people along an arrow, in the order the editor offers them ("manual" documents a hand move). */
export const TRIGGERS = ['lead_submitted', 'session_booked', 'session_done', 'session_no_show', 'payment_approved', 'enrollment_completed', 'consultancy_finished', 'manual'] as const;

export const STAGE_KINDS = ['start', 'step', 'end'] as const;

/** A stage as the editor holds it: the saved one, or a new one with an id like "new-1" until it is saved. */
export interface EditorStage {
    id: string;
    name: string;
    kind: string;
    x: number;
    y: number;
    emailTemplateId: string | null;
    alertDays: number | null;
    people: number;
}

/** An arrow as the editor holds it; its id is only for the canvas. */
export interface EditorArrow {
    id: string;
    from: string;
    to: string;
    trigger: string;
}

export function arrowId(from: string, to: string, index: number): string {
    return `${from}->${to}#${index}`;
}

export function toEditor(flow: Flow): { stages: EditorStage[]; arrows: EditorArrow[] } {
    return {
        stages: flow.stages.map((stage) => ({ ...stage, emailTemplateId: stage.emailTemplateId ?? null, alertDays: stage.alertDays ?? null })),
        arrows: flow.transitions.map((transition, index) => ({ id: arrowId(transition.from, transition.to, index), ...transition })),
    };
}

/** The PUT body: the whole canvas, positions rounded (the API keeps whole pixels). */
export function saveBody(name: string, stages: EditorStage[], arrows: EditorArrow[]) {
    return {
        name,
        stages: stages.map(({ id, name: stageName, kind, x, y, emailTemplateId, alertDays }) => ({ id, name: stageName, kind, x: Math.round(x), y: Math.round(y), emailTemplateId, alertDays })),
        transitions: arrows.map(({ from, to, trigger }) => ({ from, to, trigger })),
    };
}

/** An id for a stage not saved yet, unlike any the editor holds. */
export function newStageId(stages: ReadonlyArray<{ id: string }>): string {
    let n = stages.length + 1;
    while (stages.some((stage) => stage.id === `new-${n}`)) n++;
    return `new-${n}`;
}

export interface CanvasProblems {
    /** Messages for the whole canvas ("stages", "transitions", "name"). */
    general: string[];
    /** By stage id: its problems, one per field. */
    stages: Record<string, string[]>;
    /** By arrow id. */
    arrows: Record<string, string[]>;
}

/**
 * The API answers the canvas's problems by position ("stages[2].name", "transitions[1].trigger"); the editor shows
 * them on the stage or arrow they are about, and the rest above the canvas.
 */
export function problemsOf(errors: Record<string, string>, stages: ReadonlyArray<{ id: string }>, arrows: ReadonlyArray<{ id: string }>): CanvasProblems {
    const problems: CanvasProblems = { general: [], stages: {}, arrows: {} };
    for (const [field, message] of Object.entries(errors)) {
        const match = /^(stages|transitions)\[(\d+)\]/.exec(field);
        const target = match ? (match[1] === 'stages' ? stages : arrows)[Number(match[2])] : undefined;
        if (!match || !target) {
            problems.general.push(message);
            continue;
        }
        const bucket = match[1] === 'stages' ? problems.stages : problems.arrows;
        (bucket[target.id] ??= []).push(message);
    }
    return problems;
}
