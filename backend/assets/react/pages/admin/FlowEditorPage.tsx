import '@xyflow/react/dist/style.css';
import React, { useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Background, Controls, Handle, MarkerType, type Edge, type EdgeChange, type Node, type NodeChange, type NodeProps, Position, ReactFlow } from '@xyflow/react';
import { api } from '../../lib/api';
import { arrowId, type EditorArrow, type EditorStage, type Flow, newStageId, problemsOf, saveBody, STAGE_KINDS, toEditor, TRIGGERS } from '../../lib/flows';
import { useApi, useSubmit } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import { useTheme } from '../../lib/theme';
import type { Get } from '../../lib/types';
import { ActionButton, Alert, Badge, ErrorState, Field, Loading, PageHeader } from '../../components/ui';

type Templates = Get<'/api/admin/email-templates/all'>['items'];

/** Loads the flow and the emails its stages can send, then hands them to the editor. */
export default function FlowEditorPage() {
    const { id = '' } = useParams();
    const flow = useApi(() => api.get<Flow>(`/api/admin/flows/${id}`), [id]);
    const templates = useApi(() => api.get<Get<'/api/admin/email-templates/all'>>('/api/admin/email-templates/all'), []);

    const error = flow.error ?? templates.error;
    if (error) return <ErrorState error={error} onRetry={flow.reload} />;
    if (!flow.data || !templates.data) return <Loading />;

    return <Editor key={flow.data.id} initial={flow.data} templates={templates.data.items} />;
}

type Selection = { type: 'stage' | 'arrow'; id: string } | null;

type StageNodeData = { stage: EditorStage; problems: string[]; template: string | null };

/**
 * Flujos › a flow: its stages as boxes and the arrows between them on a canvas. Drag a box to move it, pull from its
 * right edge to another box to draw an arrow, click one to change it on the side. Nothing changes until "Guardar".
 */
function Editor({ initial, templates }: { initial: Flow; templates: Templates }) {
    const { resolved } = useTheme();
    const navigate = useNavigate();
    const [flow, setFlow] = useState(initial);
    const [name, setName] = useState(initial.name);
    const [stages, setStages] = useState<EditorStage[]>(() => toEditor(initial).stages);
    const [arrows, setArrows] = useState<EditorArrow[]>(() => toEditor(initial).arrows);
    const [selected, setSelected] = useState<Selection>(null);
    const [dirty, setDirty] = useState(false);
    const [measured, setMeasured] = useState<Record<string, { width: number; height: number }>>({});
    const [notice, setNotice] = useState<string | null>(null);
    const submit = useSubmit();
    const problems = problemsOf(submit.errors, stages, arrows);
    const templateNames = useMemo(() => new Map(templates.map((template) => [template.id, template.name])), [templates]);

    const changeStages = (next: EditorStage[]) => {
        setStages(next);
        setDirty(true);
    };
    const changeArrows = (next: EditorArrow[]) => {
        setArrows(next);
        setDirty(true);
    };

    const nodes: Node<StageNodeData>[] = stages.map((stage) => ({
        id: stage.id,
        type: 'stage',
        position: { x: stage.x, y: stage.y },
        ...(measured[stage.id] ? { measured: measured[stage.id] } : {}),
        selected: selected?.type === 'stage' && selected.id === stage.id,
        data: { stage, problems: problems.stages[stage.id] ?? [], template: stage.emailTemplateId ? (templateNames.get(stage.emailTemplateId) ?? null) : null },
    }));
    const edges: Edge[] = arrows.map((arrow) => ({
        id: arrow.id,
        source: arrow.from,
        target: arrow.to,
        label: t(`flows.trigger.${arrow.trigger}`),
        selected: selected?.type === 'arrow' && selected.id === arrow.id,
        markerEnd: { type: MarkerType.ArrowClosed },
        className: [arrow.trigger === 'manual' ? 'flow-edge-manual' : '', problems.arrows[arrow.id] ? 'has-problem' : ''].join(' '),
    }));

    const onNodesChange = (changes: NodeChange<Node<StageNodeData>>[]) => {
        let next = stages;
        for (const change of changes) {
            if (change.type === 'position' && change.position) {
                const { x, y } = change.position;
                next = next.map((stage) => (stage.id === change.id ? { ...stage, x, y } : stage));
            }
            if (change.type === 'select' && change.selected) setSelected({ type: 'stage', id: change.id });
            // The canvas measures each box once drawn, and needs the size handed back (controlled nodes).
            if (change.type === 'dimensions' && change.dimensions) {
                const { width, height } = change.dimensions;
                setMeasured((current) => (current[change.id]?.width === width && current[change.id]?.height === height ? current : { ...current, [change.id]: { width, height } }));
            }
        }
        if (next !== stages) changeStages(next);
    };
    const onEdgesChange = (changes: EdgeChange[]) => {
        for (const change of changes) {
            if (change.type === 'select' && change.selected) setSelected({ type: 'arrow', id: change.id });
        }
    };

    const addStage = () => {
        const id = newStageId(stages);
        const last = stages[stages.length - 1];
        changeStages([...stages, { id, name: t('flows.newStage'), kind: 'step', x: (last?.x ?? 0) + 240, y: last?.y ?? 0, emailTemplateId: null, alertDays: null, people: 0 }]);
        setSelected({ type: 'stage', id });
    };

    const save = async () => {
        setNotice(null);
        const result = await submit.run(() => api.put<Flow>(`/api/admin/flows/${flow.id}`, saveBody(name, stages, arrows)));
        if (!result.ok) return;
        const saved = toEditor(result.value);
        // Saved stages have their real ids now: what was selected by a "new-" id is no longer there.
        setFlow(result.value);
        setStages(saved.stages);
        setArrows(saved.arrows);
        setSelected(null);
        setDirty(false);
        setNotice(t('common.saved'));
    };

    const stage = selected?.type === 'stage' ? stages.find((candidate) => candidate.id === selected.id) : undefined;
    const arrow = selected?.type === 'arrow' ? arrows.find((candidate) => candidate.id === selected.id) : undefined;

    return (
        <>
            <p className="small">
                <Link to="/admin/flujos">← {t('nav.flows')}</Link>
            </p>
            <PageHeader
                title={name || flow.name}
                subtitle={<Badge value={flow.active ? 'active' : 'inactive'}>{flow.active ? t('flows.active') : t('flows.inactive')}</Badge>}
                actions={
                    <>
                        <ActionButton action="open" size="md" onClick={() => navigate(`/admin/prospectos?tab=tablero&flujo=${flow.id}`)} disabled={dirty}>
                            {t('flows.board')}
                        </ActionButton>
                        <ActionButton action="confirm" size="md" busy={submit.busy} disabled={!dirty} onClick={save}>
                            {dirty ? t('flows.save') : t('pageEditor.saved')}
                        </ActionButton>
                    </>
                }
            />
            <Alert kind="success" onDismiss={() => setNotice(null)}>
                {notice}
            </Alert>
            <Alert kind="error">{submit.formError && [submit.formError, ...problems.general].join(' ')}</Alert>
            <div className="flow-editor">
                <div className="flow-canvas" aria-label={t('flows.canvas')}>
                    <ReactFlow
                        nodes={nodes}
                        edges={edges}
                        nodeTypes={NODE_TYPES}
                        colorMode={resolved}
                        onNodesChange={onNodesChange}
                        onEdgesChange={onEdgesChange}
                        onConnect={({ source, target }) => {
                            if (!source || !target || source === target) return;
                            const id = arrowId(source, target, Date.now());
                            changeArrows([...arrows, { id, from: source, to: target, trigger: 'manual' }]);
                            setSelected({ type: 'arrow', id });
                        }}
                        onPaneClick={() => setSelected(null)}
                        deleteKeyCode={null}
                        fitView
                        fitViewOptions={{ padding: 0.2 }}
                    >
                        <Background gap={20} />
                        <Controls showInteractive={false} />
                    </ReactFlow>
                </div>
                <aside className="flow-panel card">
                    {stage ? (
                        <StagePanel
                            stage={stage}
                            templates={templates}
                            errors={problems.stages[stage.id] ?? []}
                            onChange={(changed) => changeStages(stages.map((candidate) => (candidate.id === changed.id ? changed : candidate)))}
                            onRemove={() => {
                                changeStages(stages.filter((candidate) => candidate.id !== stage.id));
                                changeArrows(arrows.filter((candidate) => candidate.from !== stage.id && candidate.to !== stage.id));
                                setSelected(null);
                            }}
                        />
                    ) : arrow ? (
                        <ArrowPanel
                            arrow={arrow}
                            from={stages.find((candidate) => candidate.id === arrow.from)?.name ?? ''}
                            to={stages.find((candidate) => candidate.id === arrow.to)?.name ?? ''}
                            errors={problems.arrows[arrow.id] ?? []}
                            onChange={(trigger) => changeArrows(arrows.map((candidate) => (candidate.id === arrow.id ? { ...candidate, trigger } : candidate)))}
                            onRemove={() => {
                                changeArrows(arrows.filter((candidate) => candidate.id !== arrow.id));
                                setSelected(null);
                            }}
                        />
                    ) : (
                        <>
                            <Field label={t('flows.name')} error={submit.errors.name}>
                                <input
                                    value={name}
                                    maxLength={120}
                                    onChange={(event) => {
                                        setName(event.target.value);
                                        setDirty(true);
                                    }}
                                />
                            </Field>
                            <p className="small muted">{t('flows.editorHint')}</p>
                            <div>
                                <ActionButton action="setup" onClick={addStage}>
                                    {t('flows.addStage')}
                                </ActionButton>
                            </div>
                            <h2 className="section-title">{t('flows.pages')}</h2>
                            {flow.pages.length > 0 ? (
                                <ul className="plain-list">
                                    {flow.pages.map((page) => (
                                        <li key={page.id}>
                                            <Link to={`/admin/paginas/${page.id}?tab=ajustes`}>{page.title}</Link>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="small muted">{t('flows.noPages')}</p>
                            )}
                        </>
                    )}
                </aside>
            </div>
        </>
    );
}

/** A stage on the canvas: its name, what it does on entry, how many people are in it. */
function StageNode({ data, selected }: NodeProps<Node<StageNodeData>>) {
    const { stage, problems, template } = data;
    return (
        <div className={`flow-node flow-node-${stage.kind}${selected ? ' is-selected' : ''}${problems.length ? ' has-problem' : ''}`}>
            {stage.kind !== 'start' && <Handle type="target" position={Position.Top} />}
            <div className="flow-node-kind">{t(`flows.kind.${stage.kind}`)}</div>
            <div className="flow-node-name">{stage.name || '—'}</div>
            <div className="flow-node-meta">
                {t('flows.peopleCount', { count: stage.people })}
                {template && <div>✉ {template}</div>}
                {stage.alertDays && <div>{t('flows.alertAfter', { count: stage.alertDays })}</div>}
            </div>
            {stage.kind !== 'end' && <Handle type="source" position={Position.Bottom} />}
        </div>
    );
}

const NODE_TYPES = { stage: StageNode };

function StagePanel({ stage, templates, errors, onChange, onRemove }: { stage: EditorStage; templates: Templates; errors: string[]; onChange: (stage: EditorStage) => void; onRemove: () => void }) {
    const set = (patch: Partial<EditorStage>) => onChange({ ...stage, ...patch });

    return (
        <>
            <h2 className="section-title">{t('flows.stage')}</h2>
            {errors.length > 0 && <Alert kind="error">{errors.join(' ')}</Alert>}
            <Field label={t('flows.stageName')}>
                <input value={stage.name} maxLength={80} autoFocus onChange={(event) => set({ name: event.target.value })} />
            </Field>
            <Field label={t('flows.stageKind')} hint={t(`flows.kindHint.${stage.kind}`)}>
                <select value={stage.kind} onChange={(event) => set({ kind: event.target.value })}>
                    {STAGE_KINDS.map((kind) => (
                        <option key={kind} value={kind}>
                            {t(`flows.kind.${kind}`)}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label={t('flows.stageEmail')} hint={templates.length === 0 ? t('flows.noTemplates') : t('flows.stageEmailHint')} optional>
                <select value={stage.emailTemplateId ?? ''} onChange={(event) => set({ emailTemplateId: event.target.value || null })}>
                    <option value="">{t('flows.noEmail')}</option>
                    {templates.map((template) => (
                        <option key={template.id} value={template.id}>
                            {template.name}
                            {template.active ? '' : ` (${t('common.inactive')})`}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label={t('flows.alertDays')} hint={t('flows.alertDaysHint')} optional>
                <input type="number" min={1} max={365} value={stage.alertDays ?? ''} onChange={(event) => set({ alertDays: event.target.value === '' ? null : Number(event.target.value) })} />
            </Field>
            <p className="small muted">{t('flows.peopleCount', { count: stage.people })}</p>
            <div>
                <ActionButton action="danger" disabled={stage.people > 0} title={stage.people > 0 ? t('flows.stageHasPeople') : undefined} onClick={onRemove}>
                    {t('flows.removeStage')}
                </ActionButton>
            </div>
            {stage.people > 0 && <p className="small muted">{t('flows.stageHasPeople')}</p>}
        </>
    );
}

function ArrowPanel({ arrow, from, to, errors, onChange, onRemove }: { arrow: EditorArrow; from: string; to: string; errors: string[]; onChange: (trigger: string) => void; onRemove: () => void }) {
    return (
        <>
            <h2 className="section-title">{t('flows.arrow')}</h2>
            <p>{t('flows.arrowFromTo', { from, to })}</p>
            {errors.length > 0 && <Alert kind="error">{errors.join(' ')}</Alert>}
            <Field label={t('flows.trigger')} hint={t(`flows.triggerHint.${arrow.trigger === 'manual' ? 'manual' : 'event'}`)}>
                <select value={arrow.trigger} autoFocus onChange={(event) => onChange(event.target.value)}>
                    {TRIGGERS.map((trigger) => (
                        <option key={trigger} value={trigger}>
                            {t(`flows.trigger.${trigger}`)}
                        </option>
                    ))}
                </select>
            </Field>
            <div>
                <ActionButton action="danger" onClick={onRemove}>
                    {t('flows.removeArrow')}
                </ActionButton>
            </div>
        </>
    );
}
