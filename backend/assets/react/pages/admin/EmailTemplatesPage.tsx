import React, { useState } from 'react';
import { api } from '../../lib/api';
import { useList, useSubmit } from '../../lib/hooks';
import { rowErrorMessage, t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { Actions, Alert, Button, Field, FilterBar, FormModal, IconButton, ListView, Row, RowLegend, TabIntro } from '../../components/ui';

type Template = Schema<'EmailTemplateOutput'>;

/** What a template can say about the person; the email fills each one in when it is sent. */
export const TEMPLATE_VARIABLES = ['nombre', 'asesor', 'fecha_sesion', 'enlace_reserva', 'enlace_pago', 'enlace_portal'] as const;

/** Ajustes › Correos: the emails a flow sends when someone enters one of its stages. */
export default function EmailTemplatesPage() {
    const list = useList<Template>('/api/admin/email-templates', { q: '', includeInactive: '' });
    const [editing, setEditing] = useState<Partial<Template> | null>(null);
    const [busyId, setBusyId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const toggle = async (template: Template) => {
        setBusyId(template.id);
        setError(null);
        try {
            await (template.active ? api.del(`/api/admin/email-templates/${template.id}`) : api.post(`/api/admin/email-templates/${template.id}/enable`));
            list.reload();
        } catch (err) {
            setError(rowErrorMessage(err));
        } finally {
            setBusyId(null);
        }
    };

    const newButton = <Button onClick={() => setEditing({})}>{t('templates.new')}</Button>;

    return (
        <>
            <TabIntro>{t('templates.intro')}</TabIntro>
            <FilterBar
                search={list.filters.q}
                onSearch={(q) => list.update({ q })}
                searchPlaceholder={t('templates.searchPlaceholder')}
                filters={[
                    {
                        name: 'status',
                        label: t('media.status'),
                        value: list.filters.includeInactive ? 'all' : 'active',
                        onChange: (value) => list.update({ includeInactive: value === 'all' ? '1' : '' }),
                        options: [
                            { value: 'active', label: t('templates.onlyActive') },
                            { value: 'all', label: t('templates.all') },
                        ],
                    },
                ]}
            >
                {newButton}
            </FilterBar>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            <RowLegend statuses={[{ value: 'active', label: t('templates.active') }, { value: 'inactive', label: t('templates.inactive') }]} />
            <ListView
                list={list}
                empty={t('templates.empty')}
                showAll={{ q: '', includeInactive: '' }}
                emptyAll={t('templates.emptyAll')}
                emptyAction={newButton}
                columns={[t('templates.name'), t('templates.subject')]}
                renderRow={(template) => (
                    <Row key={template.id} status={template.active ? 'active' : 'inactive'} label={template.active ? t('templates.active') : t('templates.inactive')} muted={!template.active}>
                        <td className="strong">{template.name}</td>
                        <td>{template.subject}</td>
                        <Actions>
                            <IconButton icon="pencil" label={t('common.edit')} onClick={() => setEditing(template)} />
                            <IconButton icon={template.active ? 'ban' : 'check'} label={template.active ? t('common.disable') : t('common.enable')} busy={busyId === template.id} onClick={() => toggle(template)} />
                        </Actions>
                    </Row>
                )}
            />
            {editing && (
                <TemplateForm
                    template={editing}
                    onClose={() => setEditing(null)}
                    onSaved={() => {
                        setEditing(null);
                        list.reload();
                    }}
                />
            )}
        </>
    );
}

function TemplateForm({ template, onClose, onSaved }: { template: Partial<Template>; onClose: () => void; onSaved: () => void }) {
    const [values, setValues] = useState({ name: template.name ?? '', subject: template.subject ?? '', body: template.body ?? '' });
    const submit = useSubmit();
    const set = (patch: Partial<typeof values>) => setValues({ ...values, ...patch });

    const save = async () => {
        const result = await submit.run(() => (template.id ? api.put(`/api/admin/email-templates/${template.id}`, values) : api.post('/api/admin/email-templates', values)));
        if (result.ok) onSaved();
    };

    return (
        <FormModal title={template.id ? t('templates.edit') : t('templates.new')} onClose={onClose} onSubmit={save} submit={submit} size="lg">
            <Field className="span-2" label={t('templates.name')} error={submit.errors.name} hint={t('templates.nameHint')}>
                <input value={values.name} maxLength={120} autoFocus onChange={(event) => set({ name: event.target.value })} />
            </Field>
            <Field className="span-2" label={t('templates.subject')} error={submit.errors.subject}>
                <input value={values.subject} maxLength={200} onChange={(event) => set({ subject: event.target.value })} />
            </Field>
            <Field className="span-2" label={t('templates.body')} error={submit.errors.body} hint={t('templates.bodyHint')}>
                <textarea value={values.body} maxLength={10000} rows={10} onChange={(event) => set({ body: event.target.value })} />
            </Field>
            <div className="small span-2">
                <p className="muted">{t('templates.variables')}</p>
                <ul className="plain-list">
                    {TEMPLATE_VARIABLES.map((variable) => (
                        <li key={variable}>
                            <code>{`{${variable}}`}</code> · {t(`templates.variable.${variable}`)}
                        </li>
                    ))}
                </ul>
            </div>
        </FormModal>
    );
}
