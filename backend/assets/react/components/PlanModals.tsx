import React, { useState } from 'react';
import { api } from '../lib/api';
import { useLocaleSettings } from '../lib/auth';
import { formatMoney, todayIn } from '../lib/format';
import { useApi, useSubmit } from '../lib/hooks';
import { t } from '../lib/i18n';
import { type Enrollment, MANUAL_METHODS } from '../lib/payments';
import type { Get, Schema } from '../lib/types';
import DateInput from './DateInput';
import { Alert, Field, FormModal } from './ui';

type ContactDetail = Schema<'ContactDetailOutput'>;

/**
 * "Asignar plan", or "Renovar" a used-up one: a free plan starts at once; a paid one waits for its payment and the
 * person gets the link by email.
 */
export function AssignPlanModal({ contactId, renewing, onClose, onDone }: { contactId: string; renewing?: Enrollment; onClose: () => void; onDone: (detail: ContactDetail) => void }) {
    const { locale } = useLocaleSettings();
    const plans = useApi(() => api.get<Get<'/api/admin/plans/all'>>('/api/admin/plans/all'), []);
    const active = (plans.data?.items ?? []).filter((plan) => plan.active);
    const [planId, setPlanId] = useState('');
    const submit = useSubmit();
    const chosen = active.find((plan) => plan.id === planId);

    const save = async () => {
        const path = renewing ? `/api/admin/enrollments/${renewing.id}/renew` : `/api/admin/contacts/${contactId}/enrollments`;
        const result = await submit.run(() => api.post<ContactDetail>(path, { planId }));
        if (result.ok) onDone(result.value);
    };

    return (
        <FormModal title={renewing ? t('plans.renewTitle', { plan: renewing.planName }) : t('plans.assign')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={renewing ? t('plans.renew') : t('plans.assignSubmit')}>
            {plans.data && active.length === 0 ? (
                <div className="span-2">
                    <Alert kind="warning">{t('plans.noneActive')}</Alert>
                </div>
            ) : (
                <Field className="span-2" label={t('agenda.plan')} error={submit.errors.planId} hint={chosen ? (chosen.free ? t('plans.assignFreeHint') : t('plans.assignPaidHint')) : undefined}>
                    <select value={planId} onChange={(event) => setPlanId(event.target.value)}>
                        <option value="">{t('plans.pick')}</option>
                        {active.map((plan) => (
                            <option key={plan.id} value={plan.id}>
                                {t('plans.option', { name: plan.name, price: plan.free ? t('plans.free') : formatMoney(plan.price, locale), sessions: plan.sessions })}
                            </option>
                        ))}
                    </select>
                </Field>
            )}
        </FormModal>
    );
}

/** "Registrar pago": money the owner received outside Wompi (cash, a transfer). The plan starts; they become a client. */
export function ManualPaymentModal({ enrollment, onClose, onDone }: { enrollment: Enrollment; onClose: () => void; onDone: (detail: ContactDetail) => void }) {
    const { locale, timezone } = useLocaleSettings();
    const [values, setValues] = useState({ method: 'transfer', paidOn: todayIn(timezone), note: '' });
    const submit = useSubmit();

    const save = async () => {
        const result = await submit.run(() => api.post<ContactDetail>(`/api/admin/enrollments/${enrollment.id}/payments`, values));
        if (result.ok) onDone(result.value);
    };

    return (
        <FormModal title={t('payments.record')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('payments.recordSubmit')}>
            <p className="small muted span-2">{t('payments.recordIntro', { plan: enrollment.planName, amount: formatMoney(enrollment.price, locale) })}</p>
            <Field label={t('payments.methodLabel')} error={submit.errors.method}>
                <select value={values.method} onChange={(event) => setValues({ ...values, method: event.target.value })}>
                    {MANUAL_METHODS.map((method) => (
                        <option key={method} value={method}>
                            {t(`payments.method.${method}`)}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label={t('payments.paidOn')} error={submit.errors.paidOn}>
                <DateInput value={values.paidOn} max={todayIn(timezone)} onChange={(event) => setValues({ ...values, paidOn: event.target.value })} />
            </Field>
            <Field className="span-2" label={t('payments.note')} error={submit.errors.note} optional hint={t('payments.noteHint')}>
                <textarea value={values.note} maxLength={500} rows={2} onChange={(event) => setValues({ ...values, note: event.target.value })} />
            </Field>
        </FormModal>
    );
}
