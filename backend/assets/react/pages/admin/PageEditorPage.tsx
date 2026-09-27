import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, ApiError, postForHtml } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useApi, useTabParam } from '../../lib/hooks';
import { errorMessage, t } from '../../lib/i18n';
import { type Catalog, contentOf, type PageContent, type PageDetail } from '../../lib/pages';
import type { Get } from '../../lib/types';
import type { IconName } from '../../components/Icon';
import { ActionButton, Alert, Badge, Button, ErrorState, Loading, PageHeader, TabPanel, Tabs } from '../../components/ui';
import FormEditor from './editor/FormEditor';
import PageSettings from './editor/PageSettings';
import SectionsEditor from './editor/SectionsEditor';
import SeoEditor from './editor/SeoEditor';

const TABS: { value: string; icon: IconName }[] = [
    { value: 'contenido', icon: 'file' },
    { value: 'formulario', icon: 'clipboard' },
    { value: 'seo', icon: 'globe' },
    { value: 'ajustes', icon: 'settings' },
];

/** Loads what the editor needs, then hands it over. */
export default function PageEditorPage() {
    const { id = '' } = useParams();
    const page = useApi(() => api.get<PageDetail>(`/api/admin/pages/${id}`), [id]);
    const catalog = useApi(() => api.get<Catalog>('/api/admin/pages/catalog'), []);
    const categories = useApi(() => api.get<Get<'/api/admin/categories/all'>>('/api/admin/categories/all'), []);

    const error = page.error ?? catalog.error ?? categories.error;
    if (error) return <ErrorState error={error} onRetry={page.reload} />;
    if (!page.data || !catalog.data || !categories.data) return <Loading />;

    return <Editor key={page.data.id} initial={page.data} catalog={catalog.data} categories={categories.data.items} />;
}

interface EditorProps {
    initial: PageDetail;
    catalog: Catalog;
    categories: Get<'/api/admin/categories/all'>['items'];
}

/**
 * Páginas › a page: the draft on the left, as visitors will see it on the right. Nothing reaches visitors until
 * "Publicar"; "Guardar borrador" keeps the work.
 */
function Editor({ initial, catalog, categories }: EditorProps) {
    const { me } = useAuth();
    const [tab, setTab] = useTabParam(TABS.map(({ value }) => value));
    const [page, setPage] = useState(initial);
    const [content, setContent] = useState<PageContent>(() => contentOf(initial));
    const [title, setTitle] = useState(initial.title);
    const [slug, setSlug] = useState(initial.slug);
    const [dirty, setDirty] = useState(false);
    const [busy, setBusy] = useState<'save' | 'publish' | 'home' | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [notice, setNotice] = useState<string | null>(null);
    const [failure, setFailure] = useState<string | null>(null);

    const change = (next: PageContent) => {
        setContent(next);
        setDirty(true);
        setNotice(null);
    };

    const fail = (err: unknown) => {
        if (err instanceof ApiError && err.status === 422) {
            setErrors(err.fieldErrors());
            setFailure(t('pageEditor.fixErrors'));
        } else {
            setFailure(errorMessage(err));
        }
    };

    const save = async (): Promise<PageDetail | null> => {
        setBusy('save');
        setErrors({});
        setFailure(null);
        try {
            const saved = await api.patch<PageDetail>(`/api/admin/pages/${page.id}`, { title, slug, draft: content });
            setPage(saved);
            setContent(contentOf(saved));
            setSlug(saved.slug);
            setDirty(false);
            return saved;
        } catch (err) {
            fail(err);
            return null;
        } finally {
            setBusy(null);
        }
    };

    const publish = async () => {
        if (dirty && !(await save())) return;
        setBusy('publish');
        setFailure(null);
        try {
            setPage(await api.post<PageDetail>(`/api/admin/pages/${page.id}/publish`));
            setNotice(t('pageEditor.published'));
        } catch (err) {
            fail(err);
        } finally {
            setBusy(null);
        }
    };

    const makeHome = async () => {
        setBusy('home');
        try {
            setPage(await api.post<PageDetail>(`/api/admin/pages/${page.id}/home`));
        } catch (err) {
            fail(err);
        } finally {
            setBusy(null);
        }
    };

    const tabErrors = (prefix: string) => Object.keys(errors).some((field) => field.startsWith(prefix));
    const host = window.location.host;
    const accountSlug = me?.account?.slug ?? '';

    return (
        <>
            <p className="small">
                <Link to="/admin/paginas">← {t('nav.pages')}</Link>
            </p>
            <PageHeader
                title={title || page.title}
                subtitle={
                    <>
                        <Badge value={page.status}>{t(`pages.status.${page.status}`)}</Badge> {page.path}
                        {page.hasUnpublishedChanges && page.status === 'published' && <span className="small muted"> · {t('pageEditor.unpublishedChanges')}</span>}
                    </>
                }
                actions={
                    <>
                        {page.status === 'published' && (
                            <a className="btn btn-sm btn-action btn-action-open" href={page.path} target="_blank" rel="noreferrer">
                                {t('pageEditor.viewLive')}
                            </a>
                        )}
                        <ActionButton action="edit" size="md" busy={busy === 'save'} disabled={!dirty} onClick={save}>
                            {dirty ? t('pageEditor.saveDraft') : t('pageEditor.saved')}
                        </ActionButton>
                        <ActionButton action="confirm" size="md" busy={busy === 'publish'} disabled={page.status === 'published' && !dirty && !page.hasUnpublishedChanges} onClick={publish}>
                            {t('pageEditor.publish')}
                        </ActionButton>
                    </>
                }
            />
            <Alert kind="success" onDismiss={() => setNotice(null)}>
                {notice}
            </Alert>
            <Alert kind="error" onDismiss={() => setFailure(null)}>
                {failure}
            </Alert>
            <div className="editor-layout">
                <div className="editor-panel">
                    <Tabs
                        id="page-editor"
                        variant="page"
                        label={t('pageEditor.label')}
                        value={tab}
                        onChange={setTab}
                        options={TABS.map(({ value, icon }) => ({
                            value,
                            icon,
                            label: t(`pageEditor.tab.${value}`) + (tabErrors({ contenido: 'sections', formulario: 'form', seo: 'seo', ajustes: 'settings' }[value] ?? value) ? ' •' : ''),
                        }))}
                    />
                    <TabPanel id="page-editor" value={tab}>
                        {tab === 'contenido' && <SectionsEditor catalog={catalog} content={content} onChange={change} errors={errors} />}
                        {tab === 'formulario' && <FormEditor catalog={catalog} content={content} categories={categories} onChange={change} errors={errors} />}
                        {tab === 'seo' && <SeoEditor content={content} url={`${host}${page.path}`} onChange={change} errors={errors} />}
                        {tab === 'ajustes' && (
                            <PageSettings
                                catalog={catalog}
                                content={content}
                                title={title}
                                slug={slug}
                                home={page.home}
                                host={host}
                                accountSlug={accountSlug}
                                categories={categories}
                                busy={busy === 'home'}
                                onTitle={(next) => {
                                    setTitle(next);
                                    setDirty(true);
                                }}
                                onSlug={(next) => {
                                    setSlug(next);
                                    setDirty(true);
                                }}
                                onChange={change}
                                onMakeHome={makeHome}
                                errors={errors}
                            />
                        )}
                    </TabPanel>
                </div>
                <Preview pageId={page.id} content={content} />
            </div>
        </>
    );
}

/**
 * The draft as visitors would see it, redrawn a moment after each change (not saved: the server renders what it is
 * sent). A draft the server refuses keeps the last good preview and says why.
 */
function Preview({ pageId, content }: { pageId: string; content: PageContent }) {
    const [html, setHtml] = useState<string | null>(null);
    const [stale, setStale] = useState(false);
    const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
    const latest = useRef(0);

    const render = useCallback(
        async (draft: PageContent) => {
            const request = ++latest.current;
            try {
                const next = await postForHtml(`/api/admin/pages/${pageId}/preview`, { draft });
                if (request !== latest.current) return;
                setHtml(next);
                setStale(false);
            } catch {
                if (request === latest.current) setStale(true);
            }
        },
        [pageId],
    );

    useEffect(() => {
        const timer = setTimeout(() => render(content), 500);
        return () => clearTimeout(timer);
    }, [content, render]);

    return (
        <aside className="editor-preview" aria-label={t('pageEditor.preview')}>
            <div className="editor-preview-bar">
                <span className="strong">{t('pageEditor.preview')}</span>
                <div className="editor-preview-devices" role="group" aria-label={t('pageEditor.device')}>
                    <Button variant={device === 'desktop' ? 'primary' : 'ghost'} size="sm" onClick={() => setDevice('desktop')}>
                        {t('pageEditor.desktop')}
                    </Button>
                    <Button variant={device === 'mobile' ? 'primary' : 'ghost'} size="sm" onClick={() => setDevice('mobile')}>
                        {t('pageEditor.mobile')}
                    </Button>
                </div>
            </div>
            {stale && <p className="small muted">{t('pageEditor.previewStale')}</p>}
            <div className={`editor-preview-frame is-${device}`}>
                {html === null ? <Loading /> : <iframe title={t('pageEditor.preview')} srcDoc={html} sandbox="allow-same-origin" />}
            </div>
        </aside>
    );
}
