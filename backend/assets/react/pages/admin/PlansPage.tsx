import React, { useState } from 'react';
import { api } from '../../lib/api';
import { ROLE_OWNER, useAuth, useLocaleSettings } from '../../lib/auth';
import { formatAmountForInput, formatMoney, parseAmountInput } from '../../lib/format';
import { useList, useSubmit } from '../../lib/hooks';
import { rowErrorMessage, t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import MoneyField from '../../components/MoneyField';
import { Actions, Alert, Badge, Button, Field, FilterBar, FormModal, IconButton, ListView, PageHeader, Row, RowLegend } from '../../components/ui';

type Plan = Schema<'PlanOutput'>;

/** Planes: what the consultant sells. The owner sets names and prices; the assistant sees them to book sessions. */
export default function PlansPage() {
    const { roles } = useAuth();
    const { locale } = useLocaleSettings();
    const isOwner = roles.includes(ROLE_OWNER);
    const list = useList<Plan>('/api/admin/plans', { q: '', includeInactive: '' });
    const [editing, setEditing] = useState<Partial<Plan> | null>(null);
    const [busyId, setBusyId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const toggle = async (plan: Plan) => {
        setBusyId(plan.id);
        setError(null);
        try {
            await (plan.active ? api.del(`/api/admin/plans/${plan.id}`) : api.post(`/api/admin/plans/${plan.id}/enable`));
            list.reload();
        } catch (err) {
            setError(rowErrorMessage(err));
        } finally {
            setBusyId(null);
        }
    };

    const newButton = isOwner ? <Button onClick={() => setEditing({})}>{t('plans.new')}</Button> : null;

    return (
        <>
            <PageHeader title={t('nav.plans')} subtitle={t('plans.subtitle')} />
            <FilterBar
                search={list.filters.q}
                onSearch={(q) => list.update({ q })}
                searchPlaceholder={t('plans.searchPlaceholder')}
                filters={[
                    {
                        name: 'status',
                        label: t('media.status'),
                        value: list.filters.includeInactive ? 'all' : 'active',
                        onChange: (value) => list.update({ includeInactive: value === 'all' ? '1' : '' }),
                        options: [
                            { value: 'active', label: t('plans.onlyActive') },
                            { value: 'all', label: t('plans.all') },
                        ],
                    },
                ]}
            >
                {newButton}
            </FilterBar>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            <RowLegend statuses={[{ value: 'active', label: t('plans.active') }, { value: 'inactive', label: t('plans.inactive') }]} />
            <ListView
                list={list}
                empty={t('plans.empty')}
                showAll={{ q: '', includeInactive: '' }}
                emptyAll={isOwner ? t('plans.emptyAll') : t('plans.emptyAllAssistant')}
                emptyAction={newButton}
                actions={isOwner}
                columns={[t('plans.name'), t('plans.price'), t('plans.sessions'), t('plans.duration')]}
                renderRow={(plan) => (
                    <Row key={plan.id} status={plan.active ? 'active' : 'inactive'} label={plan.active ? t('plans.active') : t('plans.inactive')} muted={!plan.active}>
                        <td>
                            <span className="strong">{plan.name}</span>
                            {plan.description && <div className="small muted">{plan.description}</div>}
                        </td>
                        <td>{plan.free ? <Badge tone="info">{t('plans.free')}</Badge> : formatMoney(plan.price, locale)}</td>
                        <td>{plan.sessions}</td>
                        <td>{t('plans.minutes', { count: plan.durationMinutes })}</td>
                        {isOwner && (
                            <Actions>
                                <IconButton icon="pencil" label={t('common.edit')} onClick={() => setEditing(plan)} />
                                <IconButton icon={plan.active ? 'ban' : 'check'} label={plan.active ? t('common.disable') : t('common.enable')} busy={busyId === plan.id} onClick={() => toggle(plan)} />
                            </Actions>
                        )}
                    </Row>
                )}
            />
            {editing && (
                <PlanForm
                    plan={editing}
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

export function PlanForm({ plan, onClose, onSaved }: { plan: Partial<Plan>; onClose: () => void; onSaved: () => void }) {
    const { locale, currency } = useLocaleSettings();
    const [values, setValues] = useState({
        name: plan.name ?? '',
        description: plan.description ?? '',
        price: plan.price ? formatAmountForInput(plan.price.amount, locale) : '',
        sessions: String(plan.sessions ?? 1),
        durationMinutes: String(plan.durationMinutes ?? 60),
    });
    const submit = useSubmit();
    const set = (patch: Partial<typeof values>) => setValues({ ...values, ...patch });

    const save = async () => {
        const body = {
            name: values.name,
            description: values.description,
            price: parseAmountInput(values.price, locale) ?? '',
            sessions: values.sessions === '' ? null : Number(values.sessions),
            durationMinutes: values.durationMinutes === '' ? null : Number(values.durationMinutes),
        };
        const result = await submit.run(() => (plan.id ? api.put(`/api/admin/plans/${plan.id}`, body) : api.post('/api/admin/plans', body)));
        if (result.ok) onSaved();
    };

    return (
        <FormModal title={plan.id ? t('plans.edit') : t('plans.new')} onClose={onClose} onSubmit={save} submit={submit}>
            <Field label={t('plans.name')} error={submit.errors.name}>
                <input value={values.name} maxLength={120} autoFocus onChange={(event) => set({ name: event.target.value })} />
            </Field>
            <Field label={t('plans.description')} error={submit.errors.description} optional hint={t('plans.descriptionHint')}>
                <textarea value={values.description} maxLength={2000} rows={3} onChange={(event) => set({ description: event.target.value })} />
            </Field>
            <MoneyField label={t('plans.price')} currency={currency} locale={locale} error={submit.errors.price} hint={t('plans.priceHint')} value={values.price} onChange={(price) => set({ price })} />
            <Field label={t('plans.sessions')} error={submit.errors.sessions}>
                <input type="number" min={1} max={50} value={values.sessions} onChange={(event) => set({ sessions: event.target.value })} />
            </Field>
            <Field label={t('plans.durationMinutes')} error={submit.errors.durationMinutes}>
                <input type="number" min={15} max={480} step={5} value={values.durationMinutes} onChange={(event) => set({ durationMinutes: event.target.value })} />
            </Field>
        </FormModal>
    );
}
