import React from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useApi } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { ErrorState, Loading, PageHeader } from '../../components/ui';

/** Plataforma › Inicio: how the platform is doing, each figure opening the list behind it. */
export default function HomePage() {
    const { me } = useAuth();
    const dashboard = useApi(() => api.get<Schema<'PlatformDashboardOutput'>>('/api/platform/dashboard'), []);
    const data = dashboard.data;

    return (
        <>
            <PageHeader title={t('platformHome.title', { name: me?.fullName ?? '' })} subtitle={t('platformHome.subtitle')} />
            {dashboard.error ? <ErrorState error={dashboard.error} onRetry={dashboard.reload} /> : null}
            {!data && !dashboard.error ? <Loading /> : null}
            {data && (
                <div className="stat-grid">
                    <Link className="stat stat-highlight" to="/plataforma/asesores?status=active">
                        <span className="stat-value">{data.activeAccounts}</span>
                        <span className="stat-label">{t('platformHome.activeAccounts', { count: data.activeAccounts })}</span>
                    </Link>
                    <Link className="stat" to="/plataforma/asesores?status=suspended">
                        <span className="stat-value">{data.suspendedAccounts}</span>
                        <span className="stat-label">{t('platformHome.suspendedAccounts', { count: data.suspendedAccounts })}</span>
                    </Link>
                    <Link className="stat" to="/plataforma/correos?status=failed">
                        <span className="stat-value">{data.failedEmailsLast7Days}</span>
                        <span className="stat-label">{t('platformHome.failedEmails', { count: data.failedEmailsLast7Days })}</span>
                    </Link>
                    <div className="stat">
                        <span className="stat-value">{data.failedJobs}</span>
                        <span className="stat-label">{t('platformHome.failedJobs', { count: data.failedJobs })}</span>
                    </div>
                </div>
            )}
            <p className="muted small">{t('platformHome.actAsHint')}</p>
        </>
    );
}
