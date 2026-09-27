import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';
import { useAuth, useLocaleSettings } from '../../lib/auth';
import { formatMoney } from '../../lib/format';
import { useApi } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { ActionButton, Actions, DataTable, IconButton, PageHeader } from '../../components/ui';

/** Inicio: where a consultant and their assistants land. Each milestone adds what needs attention today. */
export default function HomePage() {
    const { me } = useAuth();
    const navigate = useNavigate();
    const { locale } = useLocaleSettings();
    const features = me?.account?.features ?? [];
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
                    {features.includes('booking') && (
                        <Link className="stat" to="/admin/agenda">
                            <span className="stat-value">{t('adminHome.sessionsValue', { today: data.sessionsToday, tomorrow: data.sessionsTomorrow })}</span>
                            <span className="stat-label">{t('adminHome.sessions')}</span>
                        </Link>
                    )}
                    {features.includes('payments') && (
                        <Link className="stat" to="/admin/pagos">
                            <span className="stat-value">{formatMoney(data.paidLast7Days, locale)}</span>
                            <span className="stat-label">{t('adminHome.payments', { count: data.paymentsLast7Days })}</span>
                        </Link>
                    )}
                    <Link className="stat" to="/admin/paginas?status=published">
                        <span className="stat-value">{t('adminHome.pagesOf', { used: data.publishedPages, max: data.maxPublishedPages })}</span>
                        <span className="stat-label">{t('adminHome.publishedPages')}</span>
                    </Link>
                </div>
            )}
            {features.includes('flows') && data && data.overdue.length > 0 && (
                <section className="card">
                    <h2>{t('adminHome.overdue')}</h2>
                    <p className="muted">{t('adminHome.overdueHint')}</p>
                    <DataTable
                        columns={[t('contacts.name'), t('adminHome.overdueWhere'), t('adminHome.overdueDays')]}
                        rows={data.overdue}
                        renderRow={(row) => (
                            <tr key={`${row.contactId}-${row.flowId}`}>
                                <td className="strong">{row.fullName}</td>
                                <td>
                                    {row.stageName} <span className="small muted">· {row.flowName}</span>
                                </td>
                                <td>{t('board.days', { count: row.days })}</td>
                                <Actions>
                                    <ActionButton action="open" onClick={() => navigate(`/admin/prospectos?tab=tablero&flujo=${row.flowId}`)}>
                                        {t('flows.board')}
                                    </ActionButton>
                                    <IconButton icon="eye" label={t('common.view')} onClick={() => navigate(`/admin/prospectos/${row.contactId}`)} />
                                </Actions>
                            </tr>
                        )}
                    />
                </section>
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
