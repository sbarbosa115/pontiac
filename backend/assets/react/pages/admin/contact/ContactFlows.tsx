import React, { useState } from 'react';
import { api } from '../../../lib/api';
import { useLocaleSettings } from '../../../lib/auth';
import { formatDateTime } from '../../../lib/format';
import { useApi } from '../../../lib/hooks';
import { errorMessage, t } from '../../../lib/i18n';
import type { Get, Schema } from '../../../lib/types';
import { ActionButton, Actions, Alert, DataTable, Field } from '../../../components/ui';

type Contact = Schema<'ContactDetailOutput'>;
type Flows = { items: Schema<'ContactFlowOutput'>[] };

/** In Resumen: the flows the person is in and their stage in each; move them, take them out, add them to another. */
export default function ContactFlows({ contact, onChanged }: { contact: Contact; onChanged: (contact: Contact) => void }) {
    const { locale, timezone } = useLocaleSettings();
    const all = useApi(() => api.get<Get<'/api/admin/flows/all'>>('/api/admin/flows/all'), []);
    const [adding, setAdding] = useState('');
    const [busy, setBusy] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const available = (all.data?.items ?? []).filter((flow) => flow.active && !contact.flows.some((mine) => mine.flowId === flow.id));

    const run = async (key: string, action: () => Promise<Flows>) => {
        setBusy(key);
        setError(null);
        try {
            onChanged({ ...contact, flows: (await action()).items });
            return true;
        } catch (err) {
            setError(errorMessage(err));
            return false;
        } finally {
            setBusy(null);
        }
    };

    const add = async () => {
        if (await run('add', () => api.post<Flows>(`/api/admin/flows/${adding}/people`, { contactId: contact.id }))) setAdding('');
    };

    return (
        <section className="card">
            <h2 className="section-title">{t('contactFlows.title')}</h2>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            {contact.flows.length === 0 ? (
                <p className="small muted">{t('contactFlows.none')}</p>
            ) : (
                <DataTable
                    columns={[t('contactFlows.flow'), t('contactFlows.stage'), t('contactFlows.since')]}
                    rows={contact.flows}
                    renderRow={(flow) => (
                        <tr key={flow.flowId}>
                            <td className="strong">{flow.flowName}</td>
                            <td>
                                <label>
                                    <span className="visually-hidden">{t('contactFlows.moveIn', { flow: flow.flowName })}</span>
                                    <select
                                        value={flow.stageId}
                                        disabled={busy !== null || contact.anonymized}
                                        onChange={(event) => run(flow.flowId, () => api.post<Flows>(`/api/admin/flows/${flow.flowId}/people/${contact.id}/move`, { stageId: event.target.value }))}
                                    >
                                        {flow.stages.map((stage) => (
                                            <option key={stage.id} value={stage.id}>
                                                {stage.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </td>
                            <td className="nowrap">{formatDateTime(flow.enteredAt, locale, timezone)}</td>
                            <Actions>
                                <ActionButton
                                    action="danger"
                                    busy={busy === `remove-${flow.flowId}`}
                                    disabled={busy !== null}
                                    onClick={() => {
                                        if (window.confirm(t('contactFlows.confirmRemove', { name: contact.fullName, flow: flow.flowName }))) {
                                            run(`remove-${flow.flowId}`, () => api.del<Flows>(`/api/admin/flows/${flow.flowId}/people/${contact.id}`));
                                        }
                                    }}
                                >
                                    {t('contactFlows.remove')}
                                </ActionButton>
                            </Actions>
                        </tr>
                    )}
                />
            )}
            {!contact.anonymized && available.length > 0 && (
                <div className="inline-form">
                    <Field label={t('contactFlows.add')}>
                        <select value={adding} onChange={(event) => setAdding(event.target.value)}>
                            <option value="">{t('contactFlows.pick')}</option>
                            {available.map((flow) => (
                                <option key={flow.id} value={flow.id}>
                                    {flow.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <ActionButton action="setup" size="md" busy={busy === 'add'} disabled={!adding || busy !== null} onClick={add}>
                        {t('contactFlows.addSubmit')}
                    </ActionButton>
                </div>
            )}
        </section>
    );
}
