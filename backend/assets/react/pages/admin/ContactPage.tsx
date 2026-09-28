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
import ContactFiles from './contact/ContactFiles';
import ContactFlows from './contact/ContactFlows';
import ContactHistory from './contact/ContactHistory';
import ContactPlans from './contact/ContactPlans';
import ContactSessions from './contact/ContactSessions';

type Contact = Schema<'ContactDetailOutput'>;

const TABS: { value: string; icon: IconName; feature?: string }[] = [
    { value: 'resumen', icon: 'users' },
    { value: 'planes', icon: 'receipt' },
    { value: 'sesiones', icon: 'calendar', feature: 'booking' },
    { value: 'archivos', icon: 'paperclip' },
    { value: 'historial', icon: 'clock', feature: 'flows' },
];

/** A contact, in tabs: who they are, what they sent and their flows; plans and payments; sessions and notes; files; history. */
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
                {tab === 'resumen' && <Summary contact={contact} onChanged={setChanged} portal={features.includes('portal')} flows={features.includes('flows')} />}
                {tab === 'planes' && <ContactPlans contact={contact} onChanged={setChanged} />}
                {tab === 'sesiones' && <ContactSessions contact={contact} onChanged={reload} />}
                {tab === 'archivos' && <ContactFiles contact={contact} />}
                {tab === 'historial' && <ContactHistory contactId={contact.id} />}
            </TabPanel>
        </>
    );
}

/** Resumen: who they are, how they consented, their category, every form they sent. */
function Summary({ contact, onChanged, portal, flows }: { contact: Contact; onChanged: (contact: Contact) => void; portal: boolean; flows: boolean }) {
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
            {flows && <ContactFlows contact={contact} onChanged={onChanged} />}
            {portal && <PortalAccess contact={contact} onChanged={onChanged} />}
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

/** Their portal: whether they can sign in, and inviting them (again), taking the access away or giving it back. */
function PortalAccess({ contact, onChanged }: { contact: Contact; onChanged: (contact: Contact) => void }) {
    const { locale, timezone } = useLocaleSettings();
    const [busy, setBusy] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const { status, lastSignInAt } = contact.portal;

    const run = async (action: string, done?: string) => {
        setBusy(true);
        setError(null);
        setNotice(null);
        try {
            onChanged(await api.post<Contact>(`/api/admin/contacts/${contact.id}/portal/${action}`));
            if (done) setNotice(done);
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    };

    return (
        <section className="card">
            <h2 className="section-title">{t('portalAccess.title')}</h2>
            <p>
                <Badge value={status === 'active' ? 'active' : status === 'invited' ? 'invited' : status === 'disabled' ? 'inactive' : 'none'}>{t(`portalAccess.status.${status}`)}</Badge>{' '}
                {lastSignInAt && <span className="small muted">{t('portalAccess.lastSignIn', { when: formatDateTime(lastSignInAt, locale, timezone) })}</span>}
            </p>
            <p className="small muted">{t(`portalAccess.hint.${status}`)}</p>
            <Alert kind="success" onDismiss={() => setNotice(null)}>
                {notice}
            </Alert>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            {!contact.anonymized && (
                <div className="row-actions">
                    {(status === 'none' || status === 'invited') && (
                        <ActionButton action="contact" size="md" busy={busy} onClick={() => run('invitation', t('portalAccess.sent', { email: contact.email }))}>
                            {status === 'none' ? t('portalAccess.invite') : t('portalAccess.resend')}
                        </ActionButton>
                    )}
                    {status === 'disabled' && (
                        <ActionButton action="confirm" size="md" busy={busy} onClick={() => run('enable')}>
                            {t('portalAccess.enable')}
                        </ActionButton>
                    )}
                    {(status === 'active' || status === 'invited') && (
                        <ActionButton action="danger" size="md" busy={busy} onClick={() => run('disable')}>
                            {t('portalAccess.disable')}
                        </ActionButton>
                    )}
                </div>
            )}
        </section>
    );
}
