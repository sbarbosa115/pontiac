import React from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';
import { useAuth, useLocaleSettings } from '../../lib/auth';
import { formatDateTime } from '../../lib/format';
import { useApi, useList, useTabParam } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import type { Get, Schema } from '../../lib/types';
import type { IconName } from '../../components/Icon';
import { Actions, Badge, FilterBar, IconButton, ListView, PageHeader, Row, RowLegend, TabPanel, Tabs } from '../../components/ui';
import FlowBoard from './FlowBoard';

type Contact = Schema<'ContactSummaryOutput'>;

const TABS: { value: string; icon: IconName; feature?: string }[] = [
    { value: 'lista', icon: 'inbox' },
    { value: 'tablero', icon: 'flow', feature: 'flows' },
];

/** Prospectos: the list of everyone who answered the consultant's pages, and the board of each flow. */
export default function ContactsPage() {
    const { me } = useAuth();
    const features = me?.account?.features ?? [];
    const tabs = TABS.filter((tab) => !tab.feature || features.includes(tab.feature));
    const [tab, setTab] = useTabParam(tabs.map(({ value }) => value));

    return (
        <>
            <PageHeader title={t('nav.contacts')} subtitle={t('contacts.subtitle')} />
            {tabs.length > 1 && (
                <Tabs
                    id="contacts"
                    variant="page"
                    label={t('nav.contacts')}
                    value={tab}
                    onChange={setTab}
                    options={tabs.map(({ value, icon }) => ({ value, icon, label: t(`contacts.tabs.${value}`) }))}
                />
            )}
            <TabPanel id="contacts" value={tab}>
                {tab === 'lista' && <ContactsList />}
                {tab === 'tablero' && <FlowBoard embedded />}
            </TabPanel>
        </>
    );
}

/** Everyone, most recent activity first. */
function ContactsList() {
    const navigate = useNavigate();
    const { locale, timezone } = useLocaleSettings();
    const list = useList<Contact>('/api/admin/contacts', { q: '', category: '', sourcePage: '' });
    const categories = useApi(() => api.get<Get<'/api/admin/categories/all'>>('/api/admin/categories/all'), []);
    const pages = useApi(() => api.get<Get<'/api/admin/pages'>>('/api/admin/pages', { perPage: 100 }), []);

    return (
        <>
            <FilterBar
                search={list.filters.q}
                onSearch={(q) => list.update({ q })}
                searchPlaceholder={t('contacts.searchPlaceholder')}
                filters={[
                    {
                        name: 'category',
                        label: t('contacts.category'),
                        value: list.filters.category,
                        onChange: (category) => list.update({ category }),
                        options: [
                            { value: '', label: t('contacts.all') },
                            { value: 'none', label: t('contacts.noCategory') },
                            ...(categories.data?.items ?? []).map((category) => ({ value: category.id, label: category.name })),
                        ],
                    },
                    {
                        name: 'sourcePage',
                        label: t('contacts.sourcePage'),
                        value: list.filters.sourcePage,
                        onChange: (sourcePage) => list.update({ sourcePage }),
                        options: [{ value: '', label: t('contacts.all') }, ...(pages.data?.items ?? []).map((page) => ({ value: page.id, label: page.title }))],
                    },
                ]}
            />
            <RowLegend statuses={[{ value: 'lead', label: t('contacts.status.lead') }]} />
            <ListView
                list={list}
                empty={t('contacts.empty')}
                showAll={{ q: '', category: '', sourcePage: '' }}
                emptyAll={t('contacts.emptyAll')}
                columns={[t('contacts.name'), t('contacts.contact'), t('contacts.category'), t('contacts.sourcePage'), t('contacts.lastActivity')]}
                renderRow={(contact) => (
                    <Row key={contact.id} status={contact.status} label={t(`contacts.status.${contact.status}`)} muted={contact.anonymized}>
                        <td className="strong">{contact.fullName}</td>
                        <td>
                            <div className="small">{contact.email}</div>
                            {contact.phone && <div className="small">{contact.phone}</div>}
                        </td>
                        <td>{contact.category ? <Badge tone={contact.category.color}>{contact.category.name}</Badge> : <span className="muted">{t('contacts.noCategory')}</span>}</td>
                        <td>{contact.sourcePage?.title ?? '—'}</td>
                        <td className="nowrap">{formatDateTime(contact.lastActivityAt, locale, timezone)}</td>
                        <Actions>
                            <IconButton icon="eye" label={t('common.view')} onClick={() => navigate(`/admin/prospectos/${contact.id}`)} />
                        </Actions>
                    </Row>
                )}
            />
        </>
    );
}
