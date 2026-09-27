import React from 'react';
import { api } from '../../../lib/api';
import { useLocaleSettings } from '../../../lib/auth';
import { formatDateTime } from '../../../lib/format';
import { useApi } from '../../../lib/hooks';
import { messages, t } from '../../../lib/i18n';
import type { Schema } from '../../../lib/types';
import { DataTable, EmptyState, ErrorState, Loading, Row, RowLegend, TabIntro } from '../../../components/ui';

type Item = Schema<'HistoryItemOutput'>;

/** The row's status: a move, or an email that left or failed. */
export function historyStatus(item: Item): string {
    if (item.type === 'flow') return 'moved';
    return item.emailStatus === 'failed' ? 'failed' : 'sent';
}

/** What happened, in words: "Nuevo → Sesión agendada" with why, or the email's kind. */
export function historyWhat(item: Item): string {
    if (item.type === 'email') return t(`emails.kinds.${item.reason}` in messages ? `emails.kinds.${item.reason}` : 'emails.kinds.other');
    if (item.reason === 'added') return t('history.added', { stage: item.toStage ?? '' });
    if (item.reason === 'removed') return t('history.removed');
    return t('history.moved', { from: item.fromStage ?? '', to: item.toStage ?? '' });
}

/** Who or what moved them: a person of the team, an event (the arrow's trigger), or a page that feeds the flow. */
export function historyWhy(item: Item): string {
    if (item.by) return t('history.by', { name: item.by });
    if (item.reason === 'added') return t('history.fromPage');
    return t('history.because', { event: t(`flows.trigger.${item.reason}`) });
}

/** Historial: every move through a flow and every email the person was sent, newest first. */
export default function ContactHistory({ contactId }: { contactId: string }) {
    const { locale, timezone } = useLocaleSettings();
    const history = useApi(() => api.get<{ items: Item[] }>(`/api/admin/contacts/${contactId}/history`), [contactId]);

    if (history.error) return <ErrorState error={history.error} onRetry={history.reload} />;
    if (!history.data) return <Loading />;

    return (
        <>
            <TabIntro>{t('history.intro')}</TabIntro>
            {history.data.items.length === 0 ? (
                <EmptyState>{t('history.empty')}</EmptyState>
            ) : (
                <>
                    <RowLegend
                        statuses={[
                            { value: 'moved', label: t('history.status.moved') },
                            { value: 'sent', label: t('history.status.sent') },
                            { value: 'failed', label: t('history.status.failed') },
                        ]}
                    />
                    <DataTable
                        actions={false}
                        columns={[t('history.when'), t('history.what'), t('history.detail')]}
                        rows={history.data.items}
                        renderRow={(item, index) => (
                            <Row key={`${item.at}-${index}`} status={historyStatus(item)} label={t(`history.status.${historyStatus(item)}`)}>
                                <td className="nowrap">{formatDateTime(item.at, locale, timezone)}</td>
                                <td>
                                    <span className="strong">{historyWhat(item)}</span>
                                    {item.flowName && <div className="small muted">{item.flowName}</div>}
                                </td>
                                <td className="small">
                                    {item.type === 'flow' && <div>{historyWhy(item)}</div>}
                                    {item.subject && <div>✉ {item.subject}</div>}
                                </td>
                            </Row>
                        )}
                    />
                </>
            )}
        </>
    );
}
