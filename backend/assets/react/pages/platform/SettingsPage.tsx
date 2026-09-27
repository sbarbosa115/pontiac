import React, { type ReactNode, useState } from 'react';
import { api } from '../../lib/api';
import { useLocaleSettings } from '../../lib/auth';
import { formatDateTime } from '../../lib/format';
import { useApi, useForm, useList, useSubmit, useTabParam } from '../../lib/hooks';
import { errorMessage, t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import type { IconName } from '../../components/Icon';
import {
    ActionButton,
    Actions,
    Alert,
    Button,
    Checkbox,
    ErrorState,
    Field,
    FilterBar,
    FormModal,
    IconButton,
    ListView,
    Loading,
    PageHeader,
    Row,
    RowLegend,
    TabIntro,
    TabPanel,
    Tabs,
} from '../../components/ui';
import { FeatureChecks, LimitFields } from './AccountPage';

type Settings = Schema<'PlatformSettingsOutput'>;
type Admin = Schema<'SuperAdminOutput'>;
type Change = Schema<'SettingsChangeOutput'>;

const TABS: { value: string; icon: IconName }[] = [
    { value: 'general', icon: 'settings' },
    { value: 'plantillas', icon: 'file' },
    { value: 'valores', icon: 'calendar' },
    { value: 'limites', icon: 'chart' },
    { value: 'funciones', icon: 'check' },
    { value: 'legal', icon: 'book' },
    { value: 'administradores', icon: 'shield' },
    { value: 'historial', icon: 'clock' },
];

export const TEMPLATES = ['free_diagnostic', 'plan_offer', 'consultant_profile', 'event', 'lead_magnet'] as const;

/**
 * Plataforma › Configuración: Pontiac's own settings, one tab at a time, and the defaults a new consultant starts with
 * (copied in when it is created: changing them never changes an existing consultant).
 */
export default function SettingsPage() {
    const [tab, setTab] = useTabParam(TABS.map(({ value }) => value));
    const loaded = useApi(() => api.get<Settings>('/api/platform/settings'), []);
    const [saved, setSaved] = useState<Settings | null>(null);
    const settings = saved ?? loaded.data;
    const needsSettings = !['administradores', 'historial'].includes(tab);

    return (
        <>
            <PageHeader title={t('nav.platformSettings')} subtitle={t('platformSettings.subtitle')} />
            <Tabs
                id="platform-settings"
                variant="page"
                label={t('nav.platformSettings')}
                value={tab}
                onChange={setTab}
                options={TABS.map(({ value, icon }) => ({ value, icon, label: t(`platformSettings.tab.${value}`) }))}
            />
            <TabPanel id="platform-settings" value={tab}>
                {needsSettings && loaded.error ? <ErrorState error={loaded.error} onRetry={loaded.reload} /> : null}
                {needsSettings && !settings && !loaded.error ? <Loading /> : null}
                {settings && tab === 'general' && <General settings={settings} onSaved={setSaved} />}
                {settings && tab === 'plantillas' && <Templates settings={settings} onSaved={setSaved} />}
                {settings && tab === 'valores' && <BookingDefaults settings={settings} onSaved={setSaved} />}
                {settings && tab === 'limites' && <DefaultLimits settings={settings} onSaved={setSaved} />}
                {settings && tab === 'funciones' && <DefaultFeatures settings={settings} onSaved={setSaved} />}
                {settings && tab === 'legal' && <Legal settings={settings} onSaved={setSaved} />}
                {tab === 'administradores' && <Admins />}
                {tab === 'historial' && <History />}
            </TabPanel>
        </>
    );
}

interface TabProps {
    settings: Settings;
    onSaved: (settings: Settings) => void;
}

/** A tab's form: its intro, a notice once saved, and the save button; `values()` is what it sends. */
function SettingsForm({ intro, values, onSaved, submit, children }: { intro: string; values: () => object; onSaved: (settings: Settings) => void; submit: ReturnType<typeof useSubmit>; children: ReactNode }) {
    const [done, setDone] = useState(false);

    const save = async (event: React.FormEvent) => {
        event.preventDefault();
        setDone(false);
        const result = await submit.run(() => api.patch<Settings>('/api/platform/settings', values()));
        if (result.ok) {
            setDone(true);
            onSaved(result.value);
        }
    };

    return (
        <form onSubmit={save} noValidate className="card">
            <TabIntro>{intro}</TabIntro>
            <Alert kind="success">{done ? t('common.saved') : null}</Alert>
            <Alert kind="error">{submit.formError}</Alert>
            {children}
            <div className="form-actions">
                <Button type="submit" busy={submit.busy}>
                    {t('common.save')}
                </Button>
            </div>
        </form>
    );
}

/** A whole number from an input, or the text as typed so the API says what is wrong with it. */
function numberOrText(value: string): number | string {
    return value.trim() !== '' && Number.isFinite(Number(value)) ? Number(value) : value;
}

function General({ settings, onSaved }: TabProps) {
    const form = useForm({ platformName: settings.platformName, supportEmail: settings.supportEmail, senderName: settings.senderName });
    const submit = useSubmit();

    return (
        <SettingsForm intro={t('platformSettings.generalIntro')} values={() => form.values} onSaved={onSaved} submit={submit}>
            <div className="form-grid">
                <Field label={t('platformSettings.platformName')} error={submit.errors.platformName}>
                    <input {...form.bind('platformName')} />
                </Field>
                <Field label={t('platformSettings.senderName')} error={submit.errors.senderName} hint={t('platformSettings.senderNameHint')}>
                    <input {...form.bind('senderName')} />
                </Field>
                <Field label={t('platformSettings.supportEmail')} error={submit.errors.supportEmail} hint={t('platformSettings.supportEmailHint')} className="span-2">
                    <input type="email" {...form.bind('supportEmail')} />
                </Field>
            </div>
        </SettingsForm>
    );
}

function Templates({ settings, onSaved }: TabProps) {
    const [enabled, setEnabled] = useState<string[]>(settings.enabledTemplates);
    const submit = useSubmit();

    return (
        <SettingsForm intro={t('platformSettings.templatesIntro')} values={() => ({ enabledTemplates: enabled })} onSaved={onSaved} submit={submit}>
            <fieldset className="checkbox-list">
                <legend>{t('platformSettings.tab.plantillas')}</legend>
                {TEMPLATES.map((template) => (
                    <div key={template}>
                        <Checkbox
                            label={t(`templates.${template}`)}
                            checked={enabled.includes(template)}
                            onChange={(checked) => setEnabled(checked ? TEMPLATES.filter((x) => x === template || enabled.includes(x)) : enabled.filter((x) => x !== template))}
                        />
                        <p className="small muted feature-hint">{t(`templates.${template}Hint`)}</p>
                    </div>
                ))}
            </fieldset>
            {submit.errors.enabledTemplates && <span className="field-error">{submit.errors.enabledTemplates}</span>}
        </SettingsForm>
    );
}

function BookingDefaults({ settings, onSaved }: TabProps) {
    const form = useForm({
        reminderHours: settings.reminderHours.join(', '),
        minNoticeHours: String(settings.minNoticeHours),
        bookingWindowDays: String(settings.bookingWindowDays),
        clientCancelHours: String(settings.clientCancelHours),
        sessionBufferMinutes: String(settings.sessionBufferMinutes),
    });
    const submit = useSubmit();
    const values = () => ({
        reminderHours: form.values.reminderHours
            .split(/[,\s]+/)
            .filter(Boolean)
            .map(numberOrText),
        minNoticeHours: numberOrText(form.values.minNoticeHours),
        bookingWindowDays: numberOrText(form.values.bookingWindowDays),
        clientCancelHours: numberOrText(form.values.clientCancelHours),
        sessionBufferMinutes: numberOrText(form.values.sessionBufferMinutes),
    });
    const reminderError = Object.entries(submit.errors).find(([field]) => field.startsWith('reminderHours'))?.[1];

    return (
        <SettingsForm intro={t('platformSettings.bookingIntro')} values={values} onSaved={onSaved} submit={submit}>
            <div className="form-grid">
                <Field label={t('platformSettings.reminderHours')} error={reminderError} hint={t('platformSettings.reminderHoursHint')} className="span-2">
                    <input {...form.bind('reminderHours')} />
                </Field>
                <Field label={t('platformSettings.minNoticeHours')} error={submit.errors.minNoticeHours}>
                    <input type="number" min={0} {...form.bind('minNoticeHours')} />
                </Field>
                <Field label={t('platformSettings.bookingWindowDays')} error={submit.errors.bookingWindowDays}>
                    <input type="number" min={1} {...form.bind('bookingWindowDays')} />
                </Field>
                <Field label={t('platformSettings.clientCancelHours')} error={submit.errors.clientCancelHours}>
                    <input type="number" min={0} {...form.bind('clientCancelHours')} />
                </Field>
                <Field label={t('platformSettings.sessionBufferMinutes')} error={submit.errors.sessionBufferMinutes}>
                    <input type="number" min={0} {...form.bind('sessionBufferMinutes')} />
                </Field>
            </div>
        </SettingsForm>
    );
}

function DefaultLimits({ settings, onSaved }: TabProps) {
    const form = useForm({
        maxPublishedPages: String(settings.defaultMaxPublishedPages),
        maxAssistants: String(settings.defaultMaxAssistants),
        storageMb: String(settings.defaultStorageMb),
        maxFileMb: String(settings.defaultMaxFileMb),
    });
    const submit = useSubmit();
    const values = () => ({
        defaultMaxPublishedPages: numberOrText(form.values.maxPublishedPages),
        defaultMaxAssistants: numberOrText(form.values.maxAssistants),
        defaultStorageMb: numberOrText(form.values.storageMb),
        defaultMaxFileMb: numberOrText(form.values.maxFileMb),
    });

    return (
        <SettingsForm intro={t('platformSettings.limitsIntro')} values={values} onSaved={onSaved} submit={submit}>
            <LimitFields form={form} errors={submit.errors} />
        </SettingsForm>
    );
}

function DefaultFeatures({ settings, onSaved }: TabProps) {
    const [features, setFeatures] = useState<string[]>(settings.defaultFeatures);
    const submit = useSubmit();

    return (
        <SettingsForm intro={t('platformSettings.featuresIntro')} values={() => ({ defaultFeatures: features })} onSaved={onSaved} submit={submit}>
            <fieldset className="checkbox-list">
                <legend>{t('consultant.features')}</legend>
                <FeatureChecks value={features} onChange={setFeatures} />
            </fieldset>
        </SettingsForm>
    );
}

function Legal({ settings, onSaved }: TabProps) {
    const form = useForm({ termsText: settings.termsText, defaultPrivacyText: settings.defaultPrivacyText, reservedSlugs: settings.reservedSlugs.join('\n') });
    const submit = useSubmit();
    const values = () => ({
        termsText: form.values.termsText,
        defaultPrivacyText: form.values.defaultPrivacyText,
        reservedSlugs: form.values.reservedSlugs
            .split(/[\s,]+/)
            .map((slug) => slug.trim().toLowerCase())
            .filter(Boolean),
    });
    const slugError = Object.entries(submit.errors).find(([field]) => field.startsWith('reservedSlugs'))?.[1];

    return (
        <SettingsForm intro={t('platformSettings.legalIntro')} values={values} onSaved={onSaved} submit={submit}>
            <Field label={t('platformSettings.termsText')} error={submit.errors.termsText}>
                <textarea rows={8} {...form.bind('termsText')} />
            </Field>
            <Field label={t('platformSettings.defaultPrivacyText')} error={submit.errors.defaultPrivacyText} hint={t('platformSettings.defaultPrivacyTextHint')}>
                <textarea rows={8} {...form.bind('defaultPrivacyText')} />
            </Field>
            <Field label={t('platformSettings.reservedSlugs')} error={slugError} hint={t('platformSettings.reservedSlugsHint')}>
                <textarea rows={4} {...form.bind('reservedSlugs')} />
            </Field>
        </SettingsForm>
    );
}

const ADMIN_STATUSES = ['active', 'invited', 'inactive'] as const;

function Admins() {
    const { locale, timezone } = useLocaleSettings();
    const list = useList<Admin>('/api/platform/admins', { q: '' });
    const [inviting, setInviting] = useState(false);
    const [busyId, setBusyId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const act = async (admin: Admin, action: () => Promise<unknown>, successMessage?: string) => {
        setBusyId(admin.id);
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

    const toggle = (admin: Admin) => {
        if (admin.active && !window.confirm(t('admins.confirmDisable', { name: admin.fullName }))) return;
        act(admin, () => (admin.active ? api.del(`/api/platform/admins/${admin.id}`) : api.post(`/api/platform/admins/${admin.id}/enable`)));
    };

    const inviteButton = <Button onClick={() => setInviting(true)}>{t('admins.invite')}</Button>;
    const statusOf = (admin: Admin) => (!admin.active ? 'inactive' : admin.loginStatus === 'active' ? 'active' : 'invited');

    return (
        <>
            <TabIntro>{t('admins.intro')}</TabIntro>
            <FilterBar search={list.filters.q} onSearch={(q) => list.update({ q })} searchPlaceholder={t('team.searchPlaceholder')}>
                {inviteButton}
            </FilterBar>
            <Alert kind="success" onDismiss={() => setNotice(null)}>
                {notice}
            </Alert>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            <RowLegend statuses={ADMIN_STATUSES.map((status) => ({ value: status, label: t(`team.status.${status}`) }))} />
            <ListView
                list={list}
                empty={t('admins.empty')}
                showAll={{ q: '' }}
                columns={[t('team.fullName'), t('team.email'), t('team.lastSignIn')]}
                renderRow={(admin) => {
                    const status = statusOf(admin);
                    return (
                        <Row key={admin.id} status={status} label={t(`team.status.${status}`)} muted={!admin.active}>
                            <td className="strong">
                                {admin.fullName}
                                {admin.you && <span className="small muted"> · {t('admins.you')}</span>}
                            </td>
                            <td>{admin.email}</td>
                            <td className="nowrap">{admin.lastSignInAt ? formatDateTime(admin.lastSignInAt, locale, timezone) : t('team.neverSignedIn')}</td>
                            <Actions>
                                {admin.active && admin.loginStatus !== 'active' && (
                                    <ActionButton
                                        action="setup"
                                        busy={busyId === admin.id}
                                        onClick={() => act(admin, () => api.post(`/api/platform/admins/${admin.id}/resend-invitation`), t('team.invitationSent', { email: admin.email }))}
                                    >
                                        {t('team.resendInvitation')}
                                    </ActionButton>
                                )}
                                {!admin.you && (
                                    <IconButton
                                        icon={admin.active ? 'ban' : 'check'}
                                        label={admin.active ? t('common.disable') : t('common.enable')}
                                        busy={busyId === admin.id}
                                        onClick={() => toggle(admin)}
                                    />
                                )}
                            </Actions>
                        </Row>
                    );
                }}
            />
            {inviting && (
                <InviteAdmin
                    onClose={() => setInviting(false)}
                    onInvited={(admin) => {
                        setInviting(false);
                        setNotice(t('team.invitationSent', { email: admin.email }));
                        list.reload();
                    }}
                />
            )}
        </>
    );
}

function InviteAdmin({ onClose, onInvited }: { onClose: () => void; onInvited: (admin: Admin) => void }) {
    const form = useForm({ fullName: '', email: '' });
    const submit = useSubmit();

    const save = async () => {
        const result = await submit.run(() => api.post<Admin>('/api/platform/admins', form.values));
        if (result.ok) onInvited(result.value);
    };

    return (
        <FormModal title={t('admins.invite')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('team.sendInvitation')}>
            <Field label={t('team.fullName')} error={submit.errors.fullName}>
                <input {...form.bind('fullName')} autoFocus />
            </Field>
            <Field label={t('team.email')} error={submit.errors.email} hint={t('team.emailHint')}>
                <input type="email" {...form.bind('email')} />
            </Field>
        </FormModal>
    );
}

function History() {
    const { locale, timezone } = useLocaleSettings();
    const list = useList<Change>('/api/platform/settings/history', { q: '' });

    return (
        <>
            <TabIntro>{t('platformSettings.historyIntro')}</TabIntro>
            <FilterBar search={list.filters.q} onSearch={(q) => list.update({ q })} searchPlaceholder={t('platformSettings.historySearch')} />
            <ListView
                list={list}
                actions={false}
                empty={t('platformSettings.historyEmpty')}
                showAll={{ q: '' }}
                emptyAll={t('platformSettings.historyEmptyAll')}
                columns={[t('platformSettings.changedAt'), t('platformSettings.changedBy'), t('platformSettings.changes')]}
                renderRow={(change) => (
                    <tr key={change.id}>
                        <td className="nowrap">{formatDateTime(change.changedAt, locale, timezone)}</td>
                        <td>
                            <div>{change.changedBy.fullName}</div>
                            <div className="small muted">{change.changedBy.email}</div>
                        </td>
                        <td>
                            <ul className="change-list">
                                {change.changes.map((item) => (
                                    <li key={item.field}>
                                        <span className="strong">{t(`platformSettings.field.${item.field}`)}</span>
                                        {': '}
                                        <span className="change-value muted">{item.from || '—'}</span>
                                        {' → '}
                                        <span className="change-value">{item.to || '—'}</span>
                                    </li>
                                ))}
                            </ul>
                        </td>
                    </tr>
                )}
            />
        </>
    );
}
