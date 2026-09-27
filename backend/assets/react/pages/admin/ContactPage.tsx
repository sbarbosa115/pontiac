import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../../lib/api';
import { ROLE_OWNER, useAuth, useLocaleSettings } from '../../lib/auth';
import { formatDateTime } from '../../lib/format';
import { useApi } from '../../lib/hooks';
import { errorMessage, t } from '../../lib/i18n';
import type { Get, Schema } from '../../lib/types';
import { ActionButton, Alert, Badge, DefinitionList, ErrorState, Field, Loading, PageHeader } from '../../components/ui';

type Contact = Schema<'ContactDetailOutput'>;

/** A contact: who they are, how they consented, every form they sent. */
export default function ContactPage() {
    const { id = '' } = useParams();
    const { roles } = useAuth();
    const { locale, timezone } = useLocaleSettings();
    const loaded = useApi(() => api.get<Contact>(`/api/admin/contacts/${id}`), [id]);
    const categories = useApi(() => api.get<Get<'/api/admin/categories/all'>>('/api/admin/categories/all'), []);
    const [changed, setChanged] = useState<Contact | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const contact = changed?.id === id ? changed : loaded.data;

    if (loaded.error) return <ErrorState error={loaded.error} onRetry={loaded.reload} />;
    if (!contact) return <Loading />;

    const run = async (action: () => Promise<Contact>) => {
        setBusy(true);
        setError(null);
        try {
            setChanged(await action());
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    };

    const anonymize = () => {
        if (!window.confirm(t('contacts.confirmAnonymize', { name: contact.fullName }))) return;
        run(() => api.post<Contact>(`/api/admin/contacts/${contact.id}/anonymize`));
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
                    <select
                        value={contact.category?.id ?? ''}
                        disabled={busy}
                        onChange={(event) => run(() => api.patch<Contact>(`/api/admin/contacts/${contact.id}`, { categoryId: event.target.value || null }))}
                    >
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
