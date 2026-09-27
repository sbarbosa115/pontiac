import React, { useState } from 'react';
import { api } from '../../../lib/api';
import { ROLE_OWNER, useAuth, useLocaleSettings } from '../../../lib/auth';
import { formatDate, formatDateTime, formatMoney } from '../../../lib/format';
import { rowErrorMessage, t } from '../../../lib/i18n';
import { allPayments, type Enrollment, ENROLLMENT_STATUSES, enrollmentActions, methodKey, PAYMENT_STATUSES } from '../../../lib/payments';
import type { Schema } from '../../../lib/types';
import { AssignPlanModal, ManualPaymentModal } from '../../../components/PlanModals';
import { ActionButton, Actions, Alert, Button, DataTable, EmptyState, Row, RowLegend, TabIntro } from '../../../components/ui';

type Contact = Schema<'ContactDetailOutput'>;

/**
 * Planes y pagos: the plans the person has (with their progress and the actions each allows) and every payment.
 * Money is recorded by the owner only.
 */
export default function ContactPlans({ contact, onChanged }: { contact: Contact; onChanged: (contact: Contact) => void }) {
    const { roles, me } = useAuth();
    const { locale, timezone } = useLocaleSettings();
    const isOwner = roles.includes(ROLE_OWNER);
    const payments = (me?.account?.features ?? []).includes('payments');
    const [assigning, setAssigning] = useState<{ renewing?: Enrollment } | null>(null);
    const [paying, setPaying] = useState<Enrollment | null>(null);
    const [busy, setBusy] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const run = async (key: string, action: () => Promise<Contact>, done?: string) => {
        setBusy(key);
        setError(null);
        setNotice(null);
        try {
            onChanged(await action());
            if (done) setNotice(done);
        } catch (err) {
            setError(rowErrorMessage(err));
        } finally {
            setBusy(null);
        }
    };

    const copy = async (enrollment: Enrollment) => {
        try {
            await navigator.clipboard.writeText(enrollment.paymentUrl ?? '');
            setNotice(t('plans.linkCopied'));
        } catch {
            // No clipboard (an insecure origin): show the link to copy by hand.
            setNotice(t('plans.linkIs', { url: enrollment.paymentUrl ?? '' }));
        }
    };

    const finish = (enrollment: Enrollment) => {
        if (!window.confirm(t('plans.confirmFinish', { name: contact.fullName }))) return;
        run(`${enrollment.id}:finish`, () => api.post<Contact>(`/api/admin/enrollments/${enrollment.id}/finish`));
    };

    const assignButton = contact.anonymized ? null : <Button onClick={() => setAssigning({})}>{t('plans.assign')}</Button>;
    const history = allPayments(contact.enrollments);

    return (
        <>
            <TabIntro action={assignButton}>{payments ? t('plans.contactIntro') : t('plans.contactIntroNoPayments')}</TabIntro>
            <Alert kind="success" onDismiss={() => setNotice(null)}>
                {notice}
            </Alert>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            {contact.enrollments.length === 0 ? (
                <EmptyState action={assignButton}>{t('plans.contactEmpty')}</EmptyState>
            ) : (
                <>
                    <RowLegend statuses={ENROLLMENT_STATUSES.map((status) => ({ value: status, label: t(`plans.status.${status}`) }))} />
                    <DataTable
                        columns={[t('agenda.plan'), t('plans.price'), t('plans.progress'), t('plans.since')]}
                        rows={contact.enrollments}
                        renderRow={(enrollment) => {
                            const can = enrollmentActions(enrollment, isOwner);
                            return (
                                <Row key={enrollment.id} status={enrollment.status} label={t(`plans.status.${enrollment.status}`)}>
                                    <td>
                                        <span className="strong">{enrollment.planName}</span>
                                        {enrollment.outcome && <div className="small muted">{t(`plans.outcome.${enrollment.outcome}`)}</div>}
                                    </td>
                                    <td>{enrollment.free ? t('plans.free') : formatMoney(enrollment.price, locale)}</td>
                                    <td>{t('plans.used', { used: enrollment.sessionsUsed, total: enrollment.sessionsIncluded, booked: enrollment.sessionsTaken - enrollment.sessionsUsed })}</td>
                                    <td>{formatDate(enrollment.createdAt.slice(0, 10), locale)}</td>
                                    <Actions>
                                        {can.recordPayment && (
                                            <ActionButton action="confirm" onClick={() => setPaying(enrollment)}>
                                                {t('payments.record')}
                                            </ActionButton>
                                        )}
                                        {can.copyLink && (
                                            <ActionButton action="open" onClick={() => copy(enrollment)}>
                                                {t('plans.copyLink')}
                                            </ActionButton>
                                        )}
                                        {can.sendLink && (
                                            <ActionButton action="contact" busy={busy === `${enrollment.id}:send`} onClick={() => run(`${enrollment.id}:send`, () => api.post<Contact>(`/api/admin/enrollments/${enrollment.id}/send-link`), t('plans.linkSent', { email: contact.email }))}>
                                                {t('plans.sendLink')}
                                            </ActionButton>
                                        )}
                                        {can.renew && (
                                            <ActionButton action="setup" onClick={() => setAssigning({ renewing: enrollment })}>
                                                {t('plans.renew')}
                                            </ActionButton>
                                        )}
                                        {can.finish && (
                                            <ActionButton action="revert" busy={busy === `${enrollment.id}:finish`} onClick={() => finish(enrollment)}>
                                                {t('plans.finish')}
                                            </ActionButton>
                                        )}
                                        {can.cancel && (
                                            <ActionButton action="danger" busy={busy === `${enrollment.id}:cancel`} onClick={() => run(`${enrollment.id}:cancel`, () => api.post<Contact>(`/api/admin/enrollments/${enrollment.id}/cancel`))}>
                                                {t('agenda.cancelShort')}
                                            </ActionButton>
                                        )}
                                    </Actions>
                                </Row>
                            );
                        }}
                    />
                </>
            )}
            {history.length > 0 && (
                <>
                    <h2 className="section-title">{t('payments.history', { count: history.length })}</h2>
                    <RowLegend statuses={PAYMENT_STATUSES.map((status) => ({ value: status, label: t(`payments.status.${status}`) }))} />
                    <DataTable
                        actions={false}
                        columns={[t('payments.date'), t('agenda.plan'), t('payments.amount'), t('payments.methodLabel'), t('payments.reference')]}
                        rows={history}
                        renderRow={(payment) => (
                            <Row key={payment.id} status={payment.status} label={t(`payments.status.${payment.status}`)}>
                                <td>{formatDateTime(payment.paidAt ?? payment.createdAt, locale, timezone)}</td>
                                <td>{payment.planName}</td>
                                <td className="strong">{formatMoney(payment.amount, locale)}</td>
                                <td>
                                    {t(methodKey(payment.method))}
                                    {payment.manual && payment.recordedBy && <div className="small muted">{t('payments.recordedBy', { name: payment.recordedBy })}</div>}
                                    {payment.note && <div className="small muted">{payment.note}</div>}
                                </td>
                                <td className="small">{payment.reference}</td>
                            </Row>
                        )}
                    />
                </>
            )}
            {assigning && (
                <AssignPlanModal
                    contactId={contact.id}
                    renewing={assigning.renewing}
                    onClose={() => setAssigning(null)}
                    onDone={(detail) => {
                        setAssigning(null);
                        onChanged(detail);
                    }}
                />
            )}
            {paying && (
                <ManualPaymentModal
                    enrollment={paying}
                    onClose={() => setPaying(null)}
                    onDone={(detail) => {
                        setPaying(null);
                        setNotice(t('payments.recorded'));
                        onChanged(detail);
                    }}
                />
            )}
        </>
    );
}
