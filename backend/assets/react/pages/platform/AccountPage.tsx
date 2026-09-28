import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../../lib/api';
import { useApi, useForm, useSubmit, useTabParam } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import type { IconName } from '../../components/Icon';
import { Alert, Button, Checkbox, ErrorState, Field, Loading, PageHeader, TabIntro, TabPanel, Tabs } from '../../components/ui';
import TeamPage from '../admin/TeamPage';
import EmailsList from './EmailsList';

type Detail = Schema<'AccountDetailOutput'>;

const TABS: { value: string; icon: IconName }[] = [
    { value: 'datos', icon: 'file' },
    { value: 'limites', icon: 'settings' },
    { value: 'usuarios', icon: 'users' },
    { value: 'correos', icon: 'inbox' },
];

export const FEATURES = ['booking', 'payments', 'portal', 'flows'] as const;
export const LOCALES = ['es_CO', 'es_MX', 'es_PE', 'es_CL', 'es_ES', 'en_US'] as const;

/** One consultant, as the super admin sets it up: its data, limits and features, team and emails. */
export default function AccountPage() {
    const { id = '' } = useParams();
    const [tab, setTab] = useTabParam(TABS.map(({ value }) => value));
    const account = useApi(() => api.get<Detail>(`/api/platform/accounts/${id}`), [id]);
    const [saved, setSaved] = useState<Detail | null>(null);
    const detail = saved?.id === id ? saved : account.data;

    if (account.error) return <ErrorState error={account.error} onRetry={account.reload} />;
    if (!detail) return <Loading />;

    return (
        <>
            <p className="small">
                <Link to="/plataforma/asesores">← {t('nav.consultants')}</Link>
            </p>
            <PageHeader title={detail.name} subtitle={`/${detail.slug} · ${t(`consultants.statusName.${detail.active ? 'active' : 'suspended'}`)}`} />
            <Tabs
                id="consultant"
                variant="page"
                label={detail.name}
                value={tab}
                onChange={setTab}
                options={TABS.map(({ value, icon }) => ({ value, icon, label: t(`consultant.tab.${value}`) }))}
            />
            <TabPanel id="consultant" value={tab}>
                {tab === 'datos' && <DataForm key={detail.id} detail={detail} onSaved={setSaved} />}
                {tab === 'limites' && <LimitsForm detail={detail} onSaved={setSaved} />}
                {tab === 'usuarios' && <TeamPage embedded endpoint={`/api/platform/accounts/${id}/users`} manage="platform" intro={t('consultant.usersIntro')} />}
                {tab === 'correos' && (
                    <>
                        <TabIntro>{t('consultant.emailsIntro')}</TabIntro>
                        <EmailsList account={id} />
                    </>
                )}
            </TabPanel>
        </>
    );
}

function DataForm({ detail, onSaved }: { detail: Detail; onSaved: (detail: Detail) => void }) {
    const form = useForm({
        name: detail.name,
        slug: detail.slug,
        country: detail.country,
        currency: detail.currency,
        locale: detail.locale,
        timezone: detail.timezone,
    });
    const submit = useSubmit();
    const [done, setDone] = useState(false);

    const save = async (event: React.FormEvent) => {
        event.preventDefault();
        setDone(false);
        const values = { ...form.values, country: form.values.country.toUpperCase(), currency: form.values.currency.toUpperCase() };
        const result = await submit.run(() => api.patch<Detail>(`/api/platform/accounts/${detail.id}`, values));
        if (result.ok) {
            setDone(true);
            onSaved(result.value);
        }
    };

    return (
        <form onSubmit={save} noValidate className="card">
            <TabIntro>{t('consultant.dataIntro')}</TabIntro>
            <Alert kind="success">{done ? t('common.saved') : null}</Alert>
            <Alert kind="error">{submit.formError}</Alert>
            <div className="form-grid">
                <Field label={t('consultants.name')} error={submit.errors.name}>
                    <input {...form.bind('name')} />
                </Field>
                <Field label={t('consultants.slug')} error={submit.errors.slug} hint={t('consultant.slugChangeHint')}>
                    <input value={form.values.slug} onChange={(event) => form.set('slug', event.target.value.toLowerCase())} />
                </Field>
                <Field label={t('consultant.country')} error={submit.errors.country} hint={t('consultant.countryHint')}>
                    <input {...form.bind('country')} maxLength={2} />
                </Field>
                <Field label={t('consultant.currency')} error={submit.errors.currency} hint={t('consultant.currencyHint')}>
                    <input {...form.bind('currency')} maxLength={3} />
                </Field>
                <Field label={t('consultant.locale')} error={submit.errors.locale}>
                    <select {...form.bind('locale')}>
                        {LOCALES.map((locale) => (
                            <option key={locale} value={locale}>
                                {t(`consultant.locales.${locale}`)}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label={t('consultant.timezone')} error={submit.errors.timezone} hint={t('consultant.timezoneHint')}>
                    <input {...form.bind('timezone')} />
                </Field>
            </div>
            <div className="form-actions">
                <Button type="submit" busy={submit.busy}>
                    {t('common.save')}
                </Button>
            </div>
        </form>
    );
}

function LimitsForm({ detail, onSaved }: { detail: Detail; onSaved: (detail: Detail) => void }) {
    const form = useForm({
        maxPublishedPages: String(detail.maxPublishedPages),
        maxAssistants: String(detail.maxAssistants),
        storageMb: String(detail.storageMb),
        maxFileMb: String(detail.maxFileMb),
    });
    const [features, setFeatures] = useState<string[]>(detail.features);
    const submit = useSubmit();
    const [done, setDone] = useState(false);

    const save = async (event: React.FormEvent) => {
        event.preventDefault();
        setDone(false);
        const numbers = Object.fromEntries(Object.entries(form.values).map(([key, value]) => [key, value.trim() === '' ? value : Number(value)]));
        const result = await submit.run(() => api.patch<Detail>(`/api/platform/accounts/${detail.id}`, { ...numbers, features }));
        if (result.ok) {
            setDone(true);
            onSaved(result.value);
        }
    };

    return (
        <form onSubmit={save} noValidate className="card">
            <TabIntro>{t('consultant.limitsIntro')}</TabIntro>
            <Alert kind="success">{done ? t('common.saved') : null}</Alert>
            <Alert kind="error">{submit.formError}</Alert>
            <LimitFields form={form} errors={submit.errors} />
            <fieldset className="checkbox-list form-section">
                <legend>{t('consultant.features')}</legend>
                <FeatureChecks value={features} onChange={setFeatures} />
                {submit.errors.features && <span className="field-error">{submit.errors.features}</span>}
            </fieldset>
            <div className="form-actions">
                <Button type="submit" busy={submit.busy}>
                    {t('common.save')}
                </Button>
            </div>
        </form>
    );
}

type LimitForm = ReturnType<typeof useForm<{ maxPublishedPages: string; maxAssistants: string; storageMb: string; maxFileMb: string }>>;

/** The four limits, shared by a consultant's page and the platform's defaults. */
export function LimitFields({ form, errors }: { form: LimitForm; errors: Record<string, string> }) {
    return (
        <div className="form-grid">
            <Field label={t('limits.maxPublishedPages')} error={errors.maxPublishedPages ?? errors.defaultMaxPublishedPages}>
                <input type="number" min={0} {...form.bind('maxPublishedPages')} />
            </Field>
            <Field label={t('limits.maxAssistants')} error={errors.maxAssistants ?? errors.defaultMaxAssistants}>
                <input type="number" min={0} {...form.bind('maxAssistants')} />
            </Field>
            <Field label={t('limits.storageMb')} error={errors.storageMb ?? errors.defaultStorageMb}>
                <input type="number" min={0} {...form.bind('storageMb')} />
            </Field>
            <Field label={t('limits.maxFileMb')} error={errors.maxFileMb ?? errors.defaultMaxFileMb}>
                <input type="number" min={1} {...form.bind('maxFileMb')} />
            </Field>
        </div>
    );
}

/** One checkbox per feature, with what it turns on. */
export function FeatureChecks({ value, onChange }: { value: string[]; onChange: (features: string[]) => void }) {
    return (
        <>
            {FEATURES.map((feature) => (
                <div key={feature}>
                    <Checkbox
                        label={t(`features.${feature}`)}
                        checked={value.includes(feature)}
                        onChange={(checked) => onChange(checked ? FEATURES.filter((f) => f === feature || value.includes(f)) : value.filter((f) => f !== feature))}
                    />
                    <p className="small muted feature-hint">{t(`features.${feature}Hint`)}</p>
                </div>
            ))}
        </>
    );
}
