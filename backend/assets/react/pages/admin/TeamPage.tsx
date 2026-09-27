import React, { useState } from 'react';
import { api } from '../../lib/api';
import { ROLE_OWNER, useAuth, useLocaleSettings } from '../../lib/auth';
import { formatDateTime } from '../../lib/format';
import { useForm, useList, useSubmit } from '../../lib/hooks';
import { errorMessage, t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { ActionButton, Actions, Alert, Button, Field, FilterBar, FormModal, IconButton, ListView, PageHeader, Row, RowLegend, TabIntro } from '../../components/ui';

type Member = Schema<'TeamMemberOutput'>;

// A row's colour is whether the person can get in: active, invited (link sent, not used yet), or disabled.
const STATUSES = ['active', 'invited', 'inactive'] as const;

function statusOf(member: Member): (typeof STATUSES)[number] {
    if (!member.active) return 'inactive';
    return member.loginStatus === 'active' ? 'active' : 'invited';
}

/**
 * Ajustes › Equipo: the consultant and the assistants they invited. Everyone on the team sees it; only the consultant
 * invites, sends an invitation again, disables and enables.
 */
export default function TeamPage({ embedded = false }: { embedded?: boolean }) {
    const { roles } = useAuth();
    const { locale, timezone } = useLocaleSettings();
    const isOwner = roles.includes(ROLE_OWNER);
    const list = useList<Member>('/api/admin/team', { q: '' });
    const [inviting, setInviting] = useState(false);
    const [busyId, setBusyId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const act = async (member: Member, action: () => Promise<unknown>, successMessage?: string) => {
        setBusyId(member.id);
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

    const toggleActive = (member: Member) => {
        if (member.active && !window.confirm(t('team.confirmDisable', { name: member.fullName }))) return;
        act(member, () => (member.active ? api.del(`/api/admin/team/${member.id}`) : api.post(`/api/admin/team/${member.id}/enable`)));
    };

    const inviteButton = isOwner ? <Button onClick={() => setInviting(true)}>{t('team.invite')}</Button> : null;

    return (
        <>
            {embedded ? <TabIntro>{t('team.intro')}</TabIntro> : <PageHeader title={t('settings.tab.equipo')} subtitle={t('team.intro')} />}
            <FilterBar search={list.filters.q} onSearch={(q) => list.update({ q })} searchPlaceholder={t('team.searchPlaceholder')}>
                {inviteButton}
            </FilterBar>
            <Alert kind="success" onDismiss={() => setNotice(null)}>
                {notice}
            </Alert>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            <RowLegend statuses={STATUSES.map((status) => ({ value: status, label: t(`team.status.${status}`) }))} />

            <ListView
                list={list}
                empty={t('team.empty')}
                showAll={{ q: '' }}
                emptyAll={t('team.emptyAll')}
                emptyAction={inviteButton}
                actions={isOwner}
                columns={[t('team.fullName'), t('team.email'), t('team.role'), t('team.lastSignIn')]}
                renderRow={(member) => {
                    const status = statusOf(member);
                    return (
                        <Row key={member.id} status={status} label={t(`team.status.${status}`)} muted={!member.active}>
                            <td className="strong">{member.fullName}</td>
                            <td>{member.email}</td>
                            <td>{t(`team.roles.${member.role}`)}</td>
                            <td className="nowrap">{member.lastSignInAt ? formatDateTime(member.lastSignInAt, locale, timezone) : t('team.neverSignedIn')}</td>
                            {isOwner && (
                                <Actions>
                                    {member.active && member.loginStatus !== 'active' && (
                                        <ActionButton
                                            action="setup"
                                            busy={busyId === member.id}
                                            onClick={() =>
                                                act(member, () => api.post(`/api/admin/team/${member.id}/resend-invitation`), t('team.invitationSent', { email: member.email }))
                                            }
                                        >
                                            {t('team.resendInvitation')}
                                        </ActionButton>
                                    )}
                                    {member.role !== 'owner' && (
                                        <IconButton
                                            icon={member.active ? 'ban' : 'check'}
                                            label={member.active ? t('common.disable') : t('common.enable')}
                                            busy={busyId === member.id}
                                            onClick={() => toggleActive(member)}
                                        />
                                    )}
                                </Actions>
                            )}
                        </Row>
                    );
                }}
            />

            {inviting && (
                <InviteForm
                    onClose={() => setInviting(false)}
                    onInvited={(member) => {
                        setInviting(false);
                        setNotice(t('team.invitationSent', { email: member.email }));
                        list.reload();
                    }}
                />
            )}
        </>
    );
}

function InviteForm({ onClose, onInvited }: { onClose: () => void; onInvited: (member: Member) => void }) {
    const form = useForm({ fullName: '', email: '' });
    const submit = useSubmit();

    const save = async () => {
        const result = await submit.run(() => api.post<Member>('/api/admin/team', form.values));
        if (result.ok) onInvited(result.value);
    };

    return (
        <FormModal title={t('team.invite')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('team.sendInvitation')}>
            <Field label={t('team.fullName')} error={submit.errors.fullName}>
                <input {...form.bind('fullName')} autoFocus />
            </Field>
            <Field label={t('team.email')} error={submit.errors.email} hint={t('team.emailHint')}>
                <input type="email" {...form.bind('email')} />
            </Field>
        </FormModal>
    );
}
