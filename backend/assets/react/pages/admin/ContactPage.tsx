import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../../lib/api';
import { ROLE_OWNER, useAuth, useLocaleSettings } from '../../lib/auth';
import { formatDateTime } from '../../lib/format';
import { useApi, useTabParam } from '../../lib/hooks';
import { errorMessage, t } from '../../lib/i18n';
import type { Get, Schema } from '../../lib/types';
import type { IconName } from '../../components/Icon';
import { ActionButton, Alert, Badge, DefinitionList, ErrorState, Field, Loading, PageHeader, TabPanel, Tabs } from '../../components/ui';
import ContactPlans from './contact/ContactPlans';
import ContactSessions from './contact/ContactSessions';

type Contact = Schema<'ContactDetailOutput'>;

const TABS: { value: string; icon: IconName; feature?: string }[] = [
    { value: 'resumen', icon: 'users' },
    { value: 'planes', icon: 'receipt' },
    { value: 'sesiones', icon: 'calendar', feature: 'booking' },
];

/** A contact, in tabs: who they are and what they sent; their plans and payments; their sessions and notes. */
export default function ContactPage() {
    const { id = '' } = useParams();
    const { roles, me } = useAuth();
    const features = me?.account?.features ?? [];
    const tabs = TABS.filter((tab) => !tab.feature || features.includes(tab.feature));
    const [tab, setTab] = useTabParam(tabs.map(({ value }) => value));
    const loaded = useApi(() => api.get<Contact>(`/api/admin/contacts/${id}`), [id]);
    const [changed, setChanged] = useState<Contact | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const contact = changed?.id === id ? changed : loaded.data;

    if (loaded.error) return <ErrorState error={loaded.error} onRetry={loaded.reload} />;
    if (!contact) return <Loading />;

    const reload = () => {
        setChanged(null);
        loaded.reload();
    };

    const anonymize = async () => {
        if (!window.confirm(t('contacts.confirmAnonymize', { name: contact.fullName }))) return;
        setBusy(true);
        setError(null);
        try {
            setChanged(await api.post<Contact>(`/api/admin/contacts/${contact.id}/anonymize`));
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <p className="small">
                <Link to="/admin/prospectos">← {t('nav.contacts')}</Link>
            </p>
            <PageHeader
                title={contact.fullName}
                subtitle={<Badge value={contact.status}>{t(`contacts.status.${contact.status}`)}</Badge>}
                actions={
                    roles.includes(ROLE_OWNER) && !contact.anonymized ? (
                        <ActionButton action="danger" size="md" busy={busy} onClick={anonymize}>
                            {t('contacts.anonymize')}
                        </ActionButton>
                    ) : null
                }
            />
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            {contact.anonymized && <Alert kind="info">{t('contacts.anonymized')}</Alert>}
            <Tabs
                id="contact"
                variant="page"
                label={contact.fullName}
                value={tab}
                onChange={setTab}
                options={tabs.map(({ value, icon }) => ({ value, icon, label: t(`contacts.tab.${value}`) }))}
            />
            <TabPanel id="contact" value={tab}>
                {tab === 'resumen' && <Summary contact={contact} onChanged={setChanged} />}
                {tab === 'planes' && <ContactPlans contact={contact} onChanged={setChanged} />}
                {tab === 'sesiones' && <ContactSessions contact={contact} onChanged={reload} />}
            </TabPanel>
        </>
    );
}

/** Resumen: who they are, how they consented, their category, every form they sent. */
function Summary({ contact, onChanged }: { contact: Contact; onChanged: (contact: Contact) => void }) {
    const { locale, timezone } = useLocaleSettings();
    const categories = useApi(() => api.get<Get<'/api/admin/categories/all'>>('/api/admin/categories/all'), []);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const setCategory = async (categoryId: string) => {
        setBusy(true);
        setError(null);
        try {
            onChanged(await api.patch<Contact>(`/api/admin/contacts/${contact.id}`, { categoryId: categoryId || null }));
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            <section className="card">
                <DefinitionList
                    items={[
                        [t('contacts.email'), contact.email],
                        [t('contacts.phone'), contact.phone ?? '—'],
                        [t('contacts.sourcePage'), contact.sourcePage?.title ?? '—'],
                        [t('contacts.createdAt'), formatDateTime(contact.createdAt, locale, timezone)],
                        [t('contacts.consentAt'), formatDateTime(contact.consentAt, locale, timezone)],
                    ]}
                />
                <Field label={t('contacts.category')}>
                    <select value={contact.category?.id ?? ''} disabled={busy} onChange={(event) => setCategory(event.target.value)}>
                        <option value="">{t('contacts.noCategory')}</option>
                        {(categories.data?.items ?? []).map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </Field>
            </section>
            <h2 className="section-title">{t('contacts.submissions', { count: contact.submissions.length })}</h2>
            {contact.submissions.map((submission) => (
                <section key={submission.id} className="card">
                    <p className="small muted">
                        {formatDateTime(submission.submittedAt, locale, timezone)} · {submission.page.title}
                    </p>
                    {submission.answers.length > 0 ? (
                        <DefinitionList items={submission.answers.map((answer) => [answer.label, answer.value || '—'] as const)} />
                    ) : (
                        <p className="muted small">{contact.anonymized ? t('contacts.answersErased') : t('contacts.noExtraAnswers')}</p>
                    )}
                    {submission.utm.length > 0 && <p className="small muted">{submission.utm.map((utm) => `${utm.name}: ${utm.value}`).join(' · ')}</p>}
                </section>
            ))}
        </>
    );
}
