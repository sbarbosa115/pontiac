import React from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api';
import { portalPath, useAuth, useLocaleSettings } from '../../lib/auth';
import { formatDateTime, formatMoney } from '../../lib/format';
import { useApi } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { actionClass, ErrorState, Loading, PageHeader } from '../../components/ui';

/** Inicio of the portal: the next session and how each plan is going. */
export default function HomePage() {
    const { me } = useAuth();
    const { locale, timezone } = useLocaleSettings();
    const base = portalPath(me?.account?.slug);
    const overview = useApi(() => api.get<Schema<'PortalOverviewOutput'>>('/api/portal/overview'), []);

    if (overview.error) return <ErrorState error={overview.error} onRetry={overview.reload} />;
    if (!overview.data) return <Loading />;
    const { nextSession, plans, cancelHours } = overview.data;

    return (
        <>
            <PageHeader title={t('portalHome.title', { name: me?.fullName ?? '' })} subtitle={me?.account?.name} />
            <section className="card">
                <h2 className="section-title">{t('portalHome.next')}</h2>
                {nextSession ? (
                    <>
                        <p className="portal-next">{formatDateTime(nextSession.startsAt, locale, timezone)}</p>
                        <p className="muted">{t('portalHome.nextDetail', { plan: nextSession.planName, minutes: nextSession.durationMinutes })}</p>
                        <div className="row-actions">
                            {nextSession.meetingLink && (
                                <a className={actionClass('open', '', 'md')} href={nextSession.meetingLink} target="_blank" rel="noopener noreferrer">
                                    {t('portal.join')}
                                </a>
                            )}
                            <Link className={actionClass('edit', '', 'md')} to={`${base}/sesiones`}>
                                {nextSession.canChange ? t('portalHome.change') : t('portalHome.see')}
                            </Link>
                        </div>
                        <p className="small muted">{t('portalHome.cancelRule', { hours: cancelHours })}</p>
                    </>
                ) : (
                    <p className="muted">{t('portalHome.noNext')}</p>
                )}
            </section>
            <h2 className="section-title">{t('portalHome.plans')}</h2>
            {plans.length === 0 && <p className="muted">{t('portalHome.noPlans', { consultant: me?.account?.name ?? '' })}</p>}
            <div className="card-grid">
                {plans.map((plan) => (
                    <section key={plan.id} className="card">
                        <h3>{plan.planName}</h3>
                        {plan.status === 'pending_payment' ? (
                            <>
                                <p>{t('portalHome.waitingPayment', { price: formatMoney(plan.price, locale) })}</p>
                                <Link className={actionClass('confirm', '', 'md')} to={`${base}/planes`}>
                                    {t('portal.payTitle')}
                                </Link>
                            </>
                        ) : (
                            <>
                                <p className="portal-progress">{t('portalHome.progress', { used: plan.sessionsUsed, total: plan.sessionsIncluded })}</p>
                                <progress max={plan.sessionsIncluded} value={plan.sessionsUsed} aria-label={t('portalHome.progress', { used: plan.sessionsUsed, total: plan.sessionsIncluded })} />
                                {plan.sessionsTaken < plan.sessionsIncluded && (
                                    <Link className={actionClass('setup', '', 'md')} to={`${base}/sesiones`}>
                                        {t('portal.book')}
                                    </Link>
                                )}
                            </>
                        )}
                    </section>
                ))}
            </div>
        </>
    );
}
