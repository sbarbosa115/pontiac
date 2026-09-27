import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';
import type { Flow } from '../../lib/flows';
import { useList, useSubmit } from '../../lib/hooks';
import { rowErrorMessage, t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { ActionButton, Actions, Alert, Button, Field, FilterBar, FormModal, IconButton, ListView, PageHeader, Row, RowLegend } from '../../components/ui';

type FlowSummary = Schema<'FlowSummaryOutput'>;

/** Flujos: the paths people follow, from their first form to the end of the consultancy. */
export default function FlowsPage() {
    const navigate = useNavigate();
    const list = useList<FlowSummary>('/api/admin/flows', { q: '', includeInactive: '' });
    const [creating, setCreating] = useState(false);
    const [busyId, setBusyId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const toggle = async (flow: FlowSummary) => {
        setBusyId(flow.id);
        setError(null);
        try {
            await (flow.active ? api.del(`/api/admin/flows/${flow.id}`) : api.post(`/api/admin/flows/${flow.id}/enable`));
            list.reload();
        } catch (err) {
            setError(rowErrorMessage(err));
        } finally {
            setBusyId(null);
        }
    };

    const newButton = <Button onClick={() => setCreating(true)}>{t('flows.new')}</Button>;

    return (
        <>
            <PageHeader title={t('nav.flows')} subtitle={t('flows.subtitle')} />
            <FilterBar
                search={list.filters.q}
                onSearch={(q) => list.update({ q })}
                searchPlaceholder={t('flows.searchPlaceholder')}
                filters={[
                    {
                        name: 'status',
                        label: t('media.status'),
                        value: list.filters.includeInactive ? 'all' : 'active',
                        onChange: (value) => list.update({ includeInactive: value === 'all' ? '1' : '' }),
                        options: [
                            { value: 'active', label: t('flows.onlyActive') },
                            { value: 'all', label: t('flows.all') },
                        ],
                    },
                ]}
            >
                {newButton}
            </FilterBar>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            <RowLegend statuses={[{ value: 'active', label: t('flows.active') }, { value: 'inactive', label: t('flows.inactive') }]} />
            <ListView
                list={list}
                empty={t('flows.empty')}
                showAll={{ q: '', includeInactive: '' }}
                emptyAll={t('flows.emptyAll')}
                emptyAction={newButton}
                columns={[t('flows.name'), t('flows.stages'), t('flows.people')]}
                renderRow={(flow) => (
                    <Row key={flow.id} status={flow.active ? 'active' : 'inactive'} label={flow.active ? t('flows.active') : t('flows.inactive')} muted={!flow.active}>
                        <td className="strong">{flow.name}</td>
                        <td>{flow.stages}</td>
                        <td>{flow.people}</td>
                        <Actions>
                            <ActionButton action="open" onClick={() => navigate(`/admin/prospectos?tab=tablero&flujo=${flow.id}`)}>
                                {t('flows.board')}
                            </ActionButton>
                            <IconButton icon="pencil" label={t('common.edit')} onClick={() => navigate(`/admin/flujos/${flow.id}`)} />
                            <IconButton icon={flow.active ? 'ban' : 'check'} label={flow.active ? t('common.disable') : t('common.enable')} busy={busyId === flow.id} onClick={() => toggle(flow)} />
                        </Actions>
                    </Row>
                )}
            />
            {creating && <NewFlow onClose={() => setCreating(false)} onCreated={(flow) => navigate(`/admin/flujos/${flow.id}`)} />}
        </>
    );
}

/** "Nuevo flujo": a name; it starts as the ready example, to change in the editor. */
function NewFlow({ onClose, onCreated }: { onClose: () => void; onCreated: (flow: Flow) => void }) {
    const [name, setName] = useState('');
    const submit = useSubmit();

    const save = async () => {
        const result = await submit.run(() => api.post<Flow>('/api/admin/flows', { name }));
        if (result.ok) onCreated(result.value);
    };

    return (
        <FormModal title={t('flows.new')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('flows.create')}>
            <Field label={t('flows.name')} error={submit.errors.name} hint={t('flows.newHint')}>
                <input value={name} maxLength={120} autoFocus onChange={(event) => setName(event.target.value)} />
            </Field>
        </FormModal>
    );
}
