import React, { useState } from 'react';
import { api } from '../../lib/api';
import { useLocaleSettings } from '../../lib/auth';
import { formatMoney } from '../../lib/format';
import { useApi } from '../../lib/hooks';
import { errorMessage, t } from '../../lib/i18n';
import { ENROLLMENT_STATUSES, methodKey, paidWhen, PAYMENT_STATUSES } from '../../lib/payments';
import type { PortalPlan } from '../../lib/portal';
import type { Get, Schema } from '../../lib/types';
import { ActionButton, Actions, Alert, DataTable, EmptyState, ErrorState, Loading, PageHeader, Row, RowLegend } from '../../components/ui';

/** Mis planes: their plans, their progress and payments; "Pagar" a plan the consultant assigned them. */
export default function PlansPage() {
    const { locale, timezone } = useLocaleSettings();
    const plans = useApi(() => api.get<Get<'/api/portal/plans'>>('/api/portal/plans'), []);
    const [paying, setPaying] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    if (plans.error) return <ErrorState error={plans.error} onRetry={plans.reload} />;
    if (!plans.data) return <Loading />;

    const pay = async (plan: PortalPlan) => {
        setPaying(plan.id);
        setError(null);
        try {
            const { checkoutUrl } = await api.post<Schema<'CheckoutOutput'>>(`/api/portal/plans/${plan.id}/pay`);
            // Wompi's checkout; Wompi brings them back to the payment's page.
            window.location.assign(checkoutUrl);
        } catch (err) {
            setError(errorMessage(err));
            setPaying(null);
        }
    };

    const payments = plans.data.items.flatMap((plan) => plan.payments.map((payment) => ({ ...payment, planName: plan.planName }))).sort((a, b) => b.createdAt.localeCompare(a.createdAt));

    return (
        <>
            <PageHeader title={t('portal.plans')} subtitle={t('portal.plansSubtitle')} />
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            {plans.data.items.length === 0 ? (
                <EmptyState>{t('portal.noPlans')}</EmptyState>
            ) : (
                <>
                    <RowLegend statuses={ENROLLMENT_STATUSES.map((status) => ({ value: status, label: t(`plans.status.${status}`) }))} />
                    <DataTable
                        // Only a plan waiting for payment can be acted on.
                        actions={plans.data.items.some((plan) => plan.payable)}
                        columns={[t('agenda.plan'), t('plans.price'), t('plans.progress')]}
                        rows={plans.data.items}
                        renderRow={(plan) => (
                            <Row key={plan.id} status={plan.status} label={t(`plans.status.${plan.status}`)}>
                                <td className="strong">{plan.planName}</td>
                                <td>{plan.free ? t('plans.free') : formatMoney(plan.price, locale)}</td>
                                <td>{t('portal.progress', { used: plan.sessionsUsed, total: plan.sessionsIncluded })}</td>
                                {plans.data?.items.some((candidate) => candidate.payable) && (
                                    <Actions>
                                        {plan.payable && (
                                            <ActionButton action="confirm" busy={paying === plan.id} onClick={() => pay(plan)}>
                                                {t('portal.payTitle')}
                                            </ActionButton>
                                        )}
                                    </Actions>
                                )}
                            </Row>
                        )}
                    />
                </>
            )}
            {payments.length > 0 && (
                <>
                    <h2 className="section-title">{t('payments.history', { count: payments.length })}</h2>
                    <RowLegend statuses={PAYMENT_STATUSES.map((status) => ({ value: status, label: t(`payments.status.${status}`) }))} />
                    <DataTable
                        actions={false}
                        columns={[t('payments.date'), t('agenda.plan'), t('payments.amount'), t('payments.methodLabel'), t('payments.reference')]}
                        rows={payments}
                        renderRow={(payment) => (
                            <Row key={payment.reference} status={payment.status} label={t(`payments.status.${payment.status}`)}>
                                <td>{paidWhen(payment, locale, timezone)}</td>
                                <td>{payment.planName}</td>
                                <td className="strong">{formatMoney(payment.amount, locale)}</td>
                                <td>{t(methodKey(payment.method))}</td>
                                <td className="small">{payment.reference}</td>
                            </Row>
                        )}
                    />
                </>
            )}
        </>
    );
}
