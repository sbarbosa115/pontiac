import React, { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '../../lib/api';
import { useApi } from '../../lib/hooks';
import { rowErrorMessage, t } from '../../lib/i18n';
import type { Get, Schema } from '../../lib/types';
import { actionClass, Alert, Badge, EmptyState, ErrorState, FilterBar, IconButton, Loading, TabIntro } from '../../components/ui';

type Board = Schema<'BoardOutput'>;
type Card = Schema<'BoardCardOutput'>;

/**
 * Prospectos › Tablero: a flow's stages as columns and its people as cards, the longest waiting first. Drag a card
 * to any stage (or pick it under the card); a card past its stage's alert is marked.
 */
export default function FlowBoard({ embedded = false }: { embedded?: boolean }) {
    const navigate = useNavigate();
    const [params, setParams] = useSearchParams();
    const flows = useApi(() => api.get<Get<'/api/admin/flows/all'>>('/api/admin/flows/all'), []);
    const active = (flows.data?.items ?? []).filter((flow) => flow.active);
    const flowId = active.some((flow) => flow.id === params.get('flujo')) ? (params.get('flujo') ?? '') : (active[0]?.id ?? '');
    const board = useApi(() => (flowId ? api.get<Board>(`/api/admin/flows/${flowId}/board`) : Promise.resolve(null)), [flowId]);
    const [moved, setMoved] = useState<Board | null>(null);
    const [dragging, setDragging] = useState<string | null>(null);
    const [over, setOver] = useState<string | null>(null);
    const [busy, setBusy] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const data = moved?.flowId === flowId ? moved : board.data;

    const pick = (id: string) => {
        const next = new URLSearchParams(params);
        next.set('flujo', id);
        setMoved(null);
        setParams(next, { replace: true });
    };

    const move = async (card: Card, stageId: string) => {
        if (!data) return;
        const column = data.columns.find((candidate) => candidate.cards.some((c) => c.contactId === card.contactId));
        if (!column || column.stageId === stageId) return;
        // Shown at once; the board is read again from the API once the move is saved.
        setMoved({
            ...data,
            columns: data.columns.map((candidate) => ({
                ...candidate,
                cards: candidate.stageId === stageId ? [...candidate.cards, { ...card, days: 0, overdue: false }] : candidate.cards.filter((c) => c.contactId !== card.contactId),
            })),
        });
        setBusy(card.contactId);
        setError(null);
        try {
            await api.post(`/api/admin/flows/${flowId}/people/${card.contactId}/move`, { stageId });
        } catch (err) {
            setError(rowErrorMessage(err));
        } finally {
            setBusy(null);
            setMoved(null);
            board.reload();
        }
    };

    if (flows.error) return <ErrorState error={flows.error} onRetry={flows.reload} />;
    if (!flows.data) return <Loading />;
    if (active.length === 0) {
        return (
            <EmptyState
                action={
                    <Link className="btn btn-primary" to="/admin/flujos">
                        {t('board.goToFlows')}
                    </Link>
                }
            >
                {t('board.noFlows')}
            </EmptyState>
        );
    }

    const dragged = data?.columns.flatMap((column) => column.cards).find((card) => card.contactId === dragging);

    return (
        <>
            {embedded && <TabIntro>{t('board.intro')}</TabIntro>}
            <FilterBar filters={[{ name: 'flow', label: t('board.flow'), value: flowId, onChange: pick, options: active.map((flow) => ({ value: flow.id, label: flow.name })) }]}>
                <Link className={actionClass('edit', '', 'md')} to={`/admin/flujos/${flowId}`}>
                    {t('board.editFlow')}
                </Link>
            </FilterBar>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            {board.error && <ErrorState error={board.error} onRetry={board.reload} />}
            {!data ? (
                !board.error && <Loading />
            ) : (
                <div className="board" role="list" aria-label={data.flowName}>
                    {data.columns.map((column) => (
                        <section
                            key={column.stageId}
                            role="listitem"
                            aria-label={column.name}
                            className={`board-column${over === column.stageId ? ' is-over' : ''}`}
                            onDragOver={(event) => {
                                if (!dragging) return;
                                event.preventDefault();
                                setOver(column.stageId);
                            }}
                            onDragLeave={() => setOver((current) => (current === column.stageId ? null : current))}
                            onDrop={(event) => {
                                event.preventDefault();
                                setOver(null);
                                if (dragged) move(dragged, column.stageId);
                                setDragging(null);
                            }}
                        >
                            <header className="board-column-head">
                                <span className="strong">{column.name}</span> <span className="muted small">{column.cards.length}</span>
                                {column.alertDays && <div className="small muted">{t('flows.alertAfter', { count: column.alertDays })}</div>}
                            </header>
                            {column.cards.length === 0 && <p className="small muted board-empty">{t('board.emptyColumn')}</p>}
                            {column.cards.map((card) => (
                                <article
                                    key={card.contactId}
                                    className={`board-card${card.overdue ? ' is-overdue' : ''}${busy === card.contactId ? ' is-busy' : ''}`}
                                    draggable
                                    onDragStart={(event) => {
                                        event.dataTransfer.effectAllowed = 'move';
                                        event.dataTransfer.setData('text/plain', card.contactId);
                                        setDragging(card.contactId);
                                    }}
                                    onDragEnd={() => {
                                        setDragging(null);
                                        setOver(null);
                                    }}
                                >
                                    <div className="board-card-head">
                                        <span className="strong">{card.fullName}</span>
                                        <IconButton icon="eye" label={t('common.view')} onClick={() => navigate(`/admin/prospectos/${card.contactId}`)} />
                                    </div>
                                    <div className="board-card-meta">
                                        {card.category && <Badge tone={card.category.color}>{card.category.name}</Badge>}
                                        <span className={`small${card.overdue ? ' board-overdue' : ' muted'}`}>
                                            {t('board.days', { count: card.days })}
                                            {card.overdue && ` · ${t('board.overdue')}`}
                                        </span>
                                    </div>
                                    <label className="board-card-move small">
                                        <span className="visually-hidden">{t('board.moveTo', { name: card.fullName })}</span>
                                        <select value={column.stageId} disabled={busy === card.contactId} onChange={(event) => move(card, event.target.value)}>
                                            {data.columns.map((target) => (
                                                <option key={target.stageId} value={target.stageId}>
                                                    {target.stageId === column.stageId ? t('board.moveHint') : target.name}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                </article>
                            ))}
                        </section>
                    ))}
                </div>
            )}
        </>
    );
}
