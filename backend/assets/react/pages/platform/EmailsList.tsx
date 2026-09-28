import React, { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../../lib/api';
import { useLocaleSettings } from '../../lib/auth';
import { formatDateTime } from '../../lib/format';
import { useForm, useList, useSubmit } from '../../lib/hooks';
import { messages, t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { Alert, Button, Field, FilterBar, FormModal, ListView, Row, RowLegend } from '../../components/ui';

type OutgoingEmail = Schema<'OutgoingEmailOutput'>;

const STATUSES = ['sent', 'failed'] as const;

/** A kind the UI has no word for yet (a new mailer) shows as the API names it, not as a translation key. */
function kindLabel(kind: string): string {
    return `emails.kinds.${kind}` in messages ? t(`emails.kinds.${kind}`) : kind;
}

/**
 * Every attempt to send an email, newest first: Plataforma › Correos, and a consultant's own (Asesores › Correos, with
 * `account`). A failed row says why, as the mail server did.
 */
export default function EmailsList({ account }: { account?: string }) {
    const [params] = useSearchParams();
    const { locale, timezone } = useLocaleSettings();
    const list = useList<OutgoingEmail>('/api/platform/emails', { q: '', status: params.get('status') ?? '', account: account ?? '' });
    const [testing, setTesting] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);

    const testButton = account ? null : <Button onClick={() => setTesting(true)}>{t('emails.sendTest')}</Button>;

    return (
        <>
            <FilterBar
                search={list.filters.q}
                onSearch={(q) => list.update({ q })}
                searchPlaceholder={account ? t('emails.searchPlaceholderAccount') : t('emails.searchPlaceholder')}
                filters={[
                    {
                        name: 'status',
                        label: t('emails.status'),
                        value: list.filters.status,
                        onChange: (status) => list.update({ status }),
                        options: [
                            { value: '', label: t('emails.statusAll') },
                            { value: 'sent', label: t('emails.statusName.sent') },
                            { value: 'failed', label: t('emails.statusName.failed') },
                        ],
                    },
                ]}
            >
                {testButton}
            </FilterBar>
            <Alert kind="success" onDismiss={() => setNotice(null)}>
                {notice}
            </Alert>
            <RowLegend statuses={STATUSES.map((status) => ({ value: status, label: t(`emails.statusName.${status}`) }))} />
            <ListView
                list={list}
                actions={false}
                empty={t('emails.empty')}
                showAll={{ q: '', status: '' }}
                emptyAll={t('emails.emptyAll')}
                columns={account ? [t('emails.sentAt'), t('emails.recipient'), t('emails.subject'), t('emails.kind')] : [t('emails.sentAt'), t('emails.recipient'), t('emails.subject'), t('emails.account'), t('emails.kind')]}
                renderRow={(email) => (
                    <Row key={email.id} status={email.status} label={t(`emails.statusName.${email.status}`)}>
                        <td className="nowrap">{formatDateTime(email.sentAt, locale, timezone)}</td>
                        <td>{email.recipient}</td>
                        <td>
                            <div>{email.subject}</div>
                            {email.error && <div className="small text-danger">{email.error}</div>}
                        </td>
                        {!account && <td>{email.account?.name ?? <span className="muted">{t('emails.platform')}</span>}</td>}
                        <td className="nowrap">{kindLabel(email.kind)}</td>
                    </Row>
                )}
            />
            {testing && (
                <TestEmail
                    onClose={() => setTesting(false)}
                    onSent={(to) => {
                        setTesting(false);
                        setNotice(t('emails.testSent', { email: to }));
                        list.reload();
                    }}
                />
            )}
        </>
    );
}

function TestEmail({ onClose, onSent }: { onClose: () => void; onSent: (to: string) => void }) {
    const form = useForm({ to: '' });
    const submit = useSubmit();

    const send = async () => {
        const result = await submit.run(() => api.post('/api/platform/emails/test', form.values));
        if (result.ok) onSent(form.values.to);
    };

    return (
        <FormModal title={t('emails.sendTest')} onClose={onClose} onSubmit={send} submit={submit} submitLabel={t('emails.send')}>
            <p className="muted">{t('emails.testIntro')}</p>
            <Field label={t('emails.to')} error={submit.errors.to}>
                <input type="email" {...form.bind('to')} autoFocus />
            </Field>
        </FormModal>
    );
}
