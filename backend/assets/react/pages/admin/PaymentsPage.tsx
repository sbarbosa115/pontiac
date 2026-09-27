import React from 'react';
import { useNavigate } from 'react-router-dom';
import { addDays } from '../../lib/agenda';
import { useLocaleSettings } from '../../lib/auth';
import { formatDateTime, formatMoney, todayIn } from '../../lib/format';
import { useList } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import { methodKey, type Payment, PAYMENT_STATUSES } from '../../lib/payments';
import { Actions, FilterBar, IconButton, ListView, PageHeader, Row, RowLegend } from '../../components/ui';

const PERIODS = ['all', '7', '30'] as const;

/** Pagos: every payment, through Wompi or recorded by the owner, newest first. */
export default function PaymentsPage() {
    const { locale, timezone } = useLocaleSettings();
    const navigate = useNavigate();
    const today = todayIn(timezone);
    const list = useList<Payment>('/api/admin/payments', { q: '', status: '', from: '' });
    const period = PERIODS.find((days) => days !== 'all' && list.filters.from === addDays(today, -Number(days) + 1)) ?? 'all';

    return (
        <>
            <PageHeader title={t('nav.payments')} subtitle={t('payments.subtitle')} />
            <FilterBar
                search={list.filters.q}
                onSearch={(q) => list.update({ q })}
                searchPlaceholder={t('payments.searchPlaceholder')}
                filters={[
                    {
                        name: 'status',
                        label: t('agenda.status'),
                        value: list.filters.status,
                        onChange: (status) => list.update({ status }),
                        options: [{ value: '', label: t('agenda.allStatuses') }, ...PAYMENT_STATUSES.map((status) => ({ value: status, label: t(`payments.status.${status}`) }))],
                    },
                    {
                        name: 'period',
                        label: t('agenda.when'),
                        value: period,
                        onChange: (value) => list.update({ from: value === 'all' ? '' : addDays(today, -Number(value) + 1) }),
                        options: PERIODS.map((days) => ({ value: days, label: t(`payments.period.${days}`) })),
                    },
                ]}
            />
            <RowLegend statuses={PAYMENT_STATUSES.map((status) => ({ value: status, label: t(`payments.status.${status}`) }))} />
            <ListView
                list={list}
                empty={t('payments.empty')}
                showAll={{ q: '', status: '', from: '' }}
                emptyAll={t('payments.emptyAll')}
                columns={[t('payments.date'), t('agenda.contact'), t('agenda.plan'), t('payments.amount'), t('payments.methodLabel'), t('payments.reference')]}
                renderRow={(payment) => (
                    <Row key={payment.id} status={payment.status} label={t(`payments.status.${payment.status}`)}>
                        <td>{formatDateTime(payment.paidAt ?? payment.createdAt, locale, timezone)}</td>
                        <td>
                            {payment.contact.fullName}
                            <div className="small muted">{payment.contact.email}</div>
                        </td>
                        <td>{payment.planName}</td>
                        <td className="strong">{formatMoney(payment.amount, locale)}</td>
                        <td>
                            {t(methodKey(payment.method))}
                            {payment.note && <div className="small muted">{payment.note}</div>}
                        </td>
                        <td className="small">{payment.reference}</td>
                        <Actions>
                            <IconButton icon="eye" label={t('agenda.viewContact')} onClick={() => navigate(`/admin/prospectos/${payment.contact.id}?tab=planes`)} />
                        </Actions>
                    </Row>
                )}
            />
        </>
    );
}
