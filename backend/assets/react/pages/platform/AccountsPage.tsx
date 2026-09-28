import React, { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '../../lib/api';
import { useAuth, useLocaleSettings } from '../../lib/auth';
import { formatDate } from '../../lib/format';
import { useForm, useList, useSubmit } from '../../lib/hooks';
import { suggestSlug } from '../../lib/slug';
import { errorMessage, t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { ActionButton, Actions, Alert, Button, Field, FilterBar, FormModal, IconButton, ListView, PageHeader, Row, RowLegend } from '../../components/ui';

type Consultant = Schema<'AccountSummaryOutput'>;
type Detail = Schema<'AccountDetailOutput'>;

const STATUSES = ['active', 'suspended'] as const;


/**
 * Plataforma › Asesores: every consultant, created here with its owner (who gets the invitation), suspended and
 * reactivated here, opened for its data, limits, features and team.
 */
export default function AccountsPage() {
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const { impersonate } = useAuth();
    const { locale } = useLocaleSettings();
    const list = useList<Consultant>('/api/platform/accounts', { q: '', status: params.get('status') ?? '' });
    const [creating, setCreating] = useState(false);
    const [busyId, setBusyId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const act = async (consultant: Consultant, action: () => Promise<unknown>, successMessage?: string) => {
        setBusyId(consultant.id);
        setError(null);
        setNotice(null);
        try {
            await action();
            if (successMessage) setNotice(successMessage);
            list.reload();
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusyId(null);
        }
    };

    const toggleActive = (consultant: Consultant) => {
        if (consultant.active && !window.confirm(t('consultants.confirmSuspend', { name: consultant.name }))) return;
        act(consultant, () => api.post(`/api/platform/accounts/${consultant.id}/${consultant.active ? 'suspend' : 'reactivate'}`));
    };

    const actAs = (consultant: Consultant) => {
        const owner = consultant.owner;
        if (!owner) return;
        impersonate({ id: owner.id, email: owner.email, fullName: owner.fullName, role: 'owner', account: { id: consultant.id, name: consultant.name } });
        navigate('/admin');
    };

    const createButton = <Button onClick={() => setCreating(true)}>{t('consultants.new')}</Button>;

    return (
        <>
            <PageHeader title={t('nav.consultants')} subtitle={t('consultants.subtitle')} />
            <FilterBar
                search={list.filters.q}
                onSearch={(q) => list.update({ q })}
                searchPlaceholder={t('consultants.searchPlaceholder')}
                filters={[
                    {
                        name: 'status',
                        label: t('consultants.status'),
                        value: list.filters.status,
                        onChange: (status) => list.update({ status }),
                        options: [
                            { value: '', label: t('consultants.statusAll') },
                            { value: 'active', label: t('consultants.statusName.active') },
                            { value: 'suspended', label: t('consultants.statusName.suspended') },
                        ],
                    },
                ]}
            >
                {createButton}
            </FilterBar>
            <Alert kind="success" onDismiss={() => setNotice(null)}>
                {notice}
            </Alert>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            <RowLegend statuses={STATUSES.map((status) => ({ value: status, label: t(`consultants.statusName.${status}`) }))} />

            <ListView
                list={list}
                empty={t('consultants.empty')}
                showAll={{ q: '', status: '' }}
                emptyAll={t('consultants.emptyAll')}
                emptyAction={createButton}
                columns={[t('consultants.name'), t('consultants.owner'), t('consultants.people'), t('consultants.createdAt')]}
                renderRow={(consultant) => {
                    const status = consultant.active ? 'active' : 'suspended';
                    const owner = consultant.owner;
                    return (
                        <Row key={consultant.id} status={status} label={t(`consultants.statusName.${status}`)} muted={!consultant.active}>
                            <td>
                                <div className="strong">{consultant.name}</div>
                                <div className="small muted">/{consultant.slug}</div>
                            </td>
                            <td>
                                {owner ? (
                                    <>
                                        <div>{owner.fullName}</div>
                                        <div className="small muted">
                                            {owner.email} · {t(`team.status.${owner.loginStatus === 'active' ? 'active' : 'invited'}`)}
                                        </div>
                                    </>
                                ) : (
                                    <span className="muted">{t('consultants.noOwner')}</span>
                                )}
                            </td>
                            <td className="nowrap">
                                {t('consultants.assistantsCount', { count: consultant.assistants })} · {t('consultants.clientsCount', { count: consultant.clients })}
                            </td>
                            <td className="nowrap">{formatDate(consultant.createdAt.slice(0, 10), locale)}</td>
                            <Actions>
                                {consultant.active && owner?.loginStatus === 'active' && (
                                    <ActionButton action="open" onClick={() => actAs(consultant)}>
                                        {t('consultants.actAs')}
                                    </ActionButton>
                                )}
                                {consultant.active && owner && owner.loginStatus !== 'active' && (
                                    <ActionButton
                                        action="setup"
                                        busy={busyId === consultant.id}
                                        onClick={() =>
                                            act(
                                                consultant,
                                                () => api.post(`/api/platform/accounts/${consultant.id}/users/${owner.id}/resend-invitation`),
                                                t('team.invitationSent', { email: owner.email }),
                                            )
                                        }
                                    >
                                        {t('team.resendInvitation')}
                                    </ActionButton>
                                )}
                                <IconButton icon="eye" label={t('common.view')} onClick={() => navigate(`/plataforma/asesores/${consultant.id}`)} />
                                <IconButton
                                    icon={consultant.active ? 'ban' : 'check'}
                                    label={consultant.active ? t('consultants.suspend') : t('consultants.reactivate')}
                                    busy={busyId === consultant.id}
                                    onClick={() => toggleActive(consultant)}
                                />
                            </Actions>
                        </Row>
                    );
                }}
            />

            {creating && (
                <CreateConsultant
                    onClose={() => setCreating(false)}
                    onCreated={(created) => {
                        setCreating(false);
                        setNotice(t('consultants.created', { name: created.name, email: created.owner?.email ?? '' }));
                        list.reload();
                    }}
                />
            )}
        </>
    );
}

export function CreateConsultant({ onClose, onCreated }: { onClose: () => void; onCreated: (created: Detail) => void }) {
    const form = useForm({ name: '', slug: '', ownerName: '', ownerEmail: '' });
    // The address follows the name until the super admin types one of their own.
    const [slugEdited, setSlugEdited] = useState(false);
    const submit = useSubmit();

    const save = async () => {
        const result = await submit.run(() => api.post<Detail>('/api/platform/accounts', form.values));
        if (result.ok) onCreated(result.value);
    };

    return (
        <FormModal title={t('consultants.new')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('consultants.createAndInvite')}>
            <div className="form-grid">
                <Field label={t('consultants.name')} error={submit.errors.name} className="span-2">
                    <input
                        value={form.values.name}
                        onChange={(event) => {
                            form.set('name', event.target.value);
                            if (!slugEdited) form.set('slug', suggestSlug(event.target.value));
                        }}
                        autoFocus
                    />
                </Field>
                <Field label={t('consultants.slug')} error={submit.errors.slug} hint={t('consultants.slugHint', { host: window.location.host, slug: form.values.slug || '…' })} className="span-2">
                    <input
                        value={form.values.slug}
                        onChange={(event) => {
                            setSlugEdited(true);
                            form.set('slug', event.target.value.toLowerCase());
                        }}
                    />
                </Field>
                <Field label={t('consultants.ownerName')} error={submit.errors.ownerName}>
                    <input {...form.bind('ownerName')} />
                </Field>
                <Field label={t('consultants.ownerEmail')} error={submit.errors.ownerEmail} hint={t('consultants.ownerEmailHint')}>
                    <input type="email" {...form.bind('ownerEmail')} />
                </Field>
            </div>
        </FormModal>
    );
}
