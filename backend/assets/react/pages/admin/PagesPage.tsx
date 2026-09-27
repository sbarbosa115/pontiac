import React, { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '../../lib/api';
import { ROLE_OWNER, useAuth, useLocaleSettings } from '../../lib/auth';
import { formatDateTime } from '../../lib/format';
import { useApi, useForm, useList, useSubmit } from '../../lib/hooks';
import { errorMessage, t } from '../../lib/i18n';
import type { Catalog, PageDetail } from '../../lib/pages';
import { suggestSlug } from '../../lib/slug';
import type { Schema } from '../../lib/types';
import { ActionButton, Actions, Alert, Button, Field, FilterBar, FormModal, IconButton, ListView, PageHeader, Row, RowLegend } from '../../components/ui';

type Page = Schema<'PageSummaryOutput'>;

const STATUSES = ['published', 'draft', 'disabled'] as const;

/** Páginas: the consultant's landing pages, each with its address, its status and the leads it brought. */
export default function PagesPage() {
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const { roles } = useAuth();
    const { locale, timezone } = useLocaleSettings();
    const isOwner = roles.includes(ROLE_OWNER);
    const catalog = useApi(() => api.get<Catalog>('/api/admin/pages/catalog'), []);
    const list = useList<Page>('/api/admin/pages', { q: '', status: params.get('status') ?? '', template: '' });
    const [creating, setCreating] = useState(false);
    const [busyId, setBusyId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const act = async (page: Page, action: () => Promise<unknown>, successMessage?: string) => {
        setBusyId(page.id);
        setError(null);
        setNotice(null);
        try {
            await action();
            if (successMessage) setNotice(successMessage);
            list.reload();
        } catch (err) {
            const violation = (err as { violations?: { message: string }[] }).violations?.[0]?.message;
            setError(violation ?? errorMessage(err));
        } finally {
            setBusyId(null);
        }
    };

    const toggle = (page: Page) => {
        if (page.status !== 'disabled' && !window.confirm(t('pages.confirmDisable', { title: page.title }))) return;
        act(page, () => api.post(`/api/admin/pages/${page.id}/${page.status === 'disabled' ? 'reactivate' : 'disable'}`));
    };

    const createButton = <Button onClick={() => setCreating(true)}>{t('pages.new')}</Button>;

    return (
        <>
            <PageHeader title={t('nav.pages')} subtitle={t('pages.subtitle')} />
            <FilterBar
                search={list.filters.q}
                onSearch={(q) => list.update({ q })}
                searchPlaceholder={t('pages.searchPlaceholder')}
                filters={[
                    {
                        name: 'status',
                        label: t('pages.statusLabel'),
                        value: list.filters.status,
                        onChange: (status) => list.update({ status }),
                        options: [{ value: '', label: t('pages.all') }, ...STATUSES.map((status) => ({ value: status, label: t(`pages.status.${status}`) }))],
                    },
                    {
                        name: 'template',
                        label: t('pages.template'),
                        value: list.filters.template,
                        onChange: (template) => list.update({ template }),
                        options: [{ value: '', label: t('pages.all') }, ...(catalog.data?.templates ?? []).map((template) => ({ value: template.key, label: t(`templates.${template.key}`) }))],
                    },
                ]}
            >
                {createButton}
            </FilterBar>
            <Alert kind="success" onDismiss={() => setNotice(null)}>
                {notice}
            </Alert>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            <RowLegend statuses={STATUSES.map((status) => ({ value: status, label: t(`pages.status.${status}`) }))} />
            <ListView
                list={list}
                empty={t('pages.empty')}
                showAll={{ q: '', status: '', template: '' }}
                emptyAll={t('pages.emptyAll')}
                emptyAction={createButton}
                columns={[t('pages.title'), t('pages.template'), t('pages.leads'), t('pages.updatedAt')]}
                renderRow={(page) => (
                    <Row key={page.id} status={page.status} label={t(`pages.status.${page.status}`)} muted={page.status === 'disabled'}>
                        <td>
                            <div className="strong">
                                {page.title}
                                {page.home && <span className="small muted"> · {t('pages.home')}</span>}
                            </div>
                            <div className="small muted">
                                {page.path}
                                {page.status === 'published' && page.hasUnpublishedChanges && ` · ${t('pageEditor.unpublishedChanges')}`}
                            </div>
                        </td>
                        <td>{t(`templates.${page.template}`)}</td>
                        <td className="nowrap">{t('pages.leadsCount', { count: page.leadsLast30Days })}</td>
                        <td className="nowrap">{formatDateTime(page.updatedAt, locale, timezone)}</td>
                        <Actions>
                            {page.status !== 'disabled' && (page.status === 'draft' || page.hasUnpublishedChanges) && (
                                <ActionButton action="confirm" busy={busyId === page.id} onClick={() => act(page, () => api.post(`/api/admin/pages/${page.id}/publish`), t('pages.publishedNotice', { title: page.title }))}>
                                    {t('pageEditor.publish')}
                                </ActionButton>
                            )}
                            <ActionButton
                                action="setup"
                                busy={busyId === page.id}
                                onClick={() => act(page, async () => navigate(`/admin/paginas/${(await api.post<PageDetail>(`/api/admin/pages/${page.id}/duplicate`)).id}`))}
                            >
                                {t('pages.duplicate')}
                            </ActionButton>
                            {page.status === 'published' && <IconButton icon="eye" label={t('pages.view')} onClick={() => window.open(page.path, '_blank', 'noopener')} />}
                            <IconButton icon="pencil" label={t('common.edit')} onClick={() => navigate(`/admin/paginas/${page.id}`)} />
                            {isOwner && (
                                <IconButton
                                    icon={page.status === 'disabled' ? 'check' : 'ban'}
                                    label={page.status === 'disabled' ? t('pages.reactivate') : t('pages.disable')}
                                    busy={busyId === page.id}
                                    onClick={() => toggle(page)}
                                />
                            )}
                        </Actions>
                    </Row>
                )}
            />
            {creating && catalog.data && (
                <CreatePage
                    catalog={catalog.data}
                    onClose={() => setCreating(false)}
                    onCreated={(page) => {
                        setCreating(false);
                        navigate(`/admin/paginas/${page.id}`);
                    }}
                />
            )}
        </>
    );
}

export function CreatePage({ catalog, onClose, onCreated }: { catalog: Catalog; onClose: () => void; onCreated: (page: PageDetail) => void }) {
    const { me } = useAuth();
    const templates = catalog.templates.filter((template) => template.enabled);
    const form = useForm({ title: '', slug: '', template: templates[0]?.key ?? '' });
    const [slugEdited, setSlugEdited] = useState(false);
    const submit = useSubmit();

    const save = async () => {
        const result = await submit.run(() => api.post<PageDetail>('/api/admin/pages', form.values));
        if (result.ok) onCreated(result.value);
    };

    return (
        <FormModal title={t('pages.new')} onClose={onClose} onSubmit={save} submit={submit} submitLabel={t('pages.create')}>
            <Field label={t('pages.title')} error={submit.errors.title}>
                <input
                    value={form.values.title}
                    onChange={(event) => {
                        form.set('title', event.target.value);
                        if (!slugEdited) form.set('slug', suggestSlug(event.target.value));
                    }}
                    autoFocus
                />
            </Field>
            <Field label={t('pages.slug')} error={submit.errors.slug} hint={t('pageEditor.address', { url: `${window.location.host}/${me?.account?.slug ?? ''}/${form.values.slug || '…'}` })}>
                <input
                    value={form.values.slug}
                    onChange={(event) => {
                        setSlugEdited(true);
                        form.set('slug', event.target.value.toLowerCase());
                    }}
                />
            </Field>
            <fieldset className="template-picker">
                <legend>{t('pages.template')}</legend>
                {templates.map((template) => (
                    <label key={template.key} className={`template-option${form.values.template === template.key ? ' is-selected' : ''}`}>
                        <input type="radio" name="template" value={template.key} checked={form.values.template === template.key} onChange={() => form.set('template', template.key)} />
                        <span>
                            <span className="strong">{t(`templates.${template.key}`)}</span>
                            <span className="small muted">{t(`templates.${template.key}Hint`)}</span>
                        </span>
                    </label>
                ))}
                {submit.errors.template && <span className="field-error">{submit.errors.template}</span>}
            </fieldset>
        </FormModal>
    );
}
