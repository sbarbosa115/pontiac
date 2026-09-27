import React from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useApi } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { PageHeader } from '../../components/ui';

/** Inicio: where a consultant and their assistants land. Each milestone adds what needs attention today. */
export default function HomePage() {
    const { me } = useAuth();
    const slug = me?.account?.slug ?? '';
    const dashboard = useApi(() => api.get<Schema<'AdminDashboardOutput'>>('/api/admin/dashboard'), []);
    const data = dashboard.data;

    return (
        <>
            <PageHeader title={t('adminHome.title', { name: me?.fullName ?? '' })} subtitle={me?.account?.name} />
            {data && (
                <div className="stat-grid">
                    <Link className="stat stat-highlight" to="/admin/prospectos">
                        <span className="stat-value">{data.newLeadsLast7Days}</span>
                        <span className="stat-label">{t('adminHome.newLeads', { count: data.newLeadsLast7Days })}</span>
                    </Link>
                    <Link className="stat" to="/admin/paginas?status=published">
                        <span className="stat-value">{t('adminHome.pagesOf', { used: data.publishedPages, max: data.maxPublishedPages })}</span>
                        <span className="stat-label">{t('adminHome.publishedPages')}</span>
                    </Link>
                </div>
            )}
            <section className="card">
                <h2>{t('adminHome.publicAddress')}</h2>
                <p className="muted">{t('adminHome.publicAddressHint')}</p>
                <p>
                    <a href={`/${slug}`} target="_blank" rel="noreferrer">
                        {`${window.location.host}/${slug}`}
                    </a>
                </p>
            </section>
        </>
    );
}
