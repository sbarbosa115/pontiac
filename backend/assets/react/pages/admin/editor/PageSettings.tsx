import React from 'react';
import { t } from '../../../lib/i18n';
import type { Catalog, PageContent } from '../../../lib/pages';
import { api } from '../../../lib/api';
import { useAuth } from '../../../lib/auth';
import { useApi } from '../../../lib/hooks';
import type { Get, Schema } from '../../../lib/types';
import { ActionButton, Field, TabIntro } from '../../../components/ui';

type Category = Schema<'LeadCategoryOutput'>;

interface Props {
    catalog: Catalog;
    content: PageContent;
    title: string;
    slug: string;
    home: boolean;
    host: string;
    accountSlug: string;
    categories: Category[];
    onTitle: (title: string) => void;
    onSlug: (slug: string) => void;
    onChange: (content: PageContent) => void;
    onMakeHome: () => void;
    busy: boolean;
    errors: Record<string, string>;
}

/** Ajustes: the page's name and address, whether it is the home page, its leads' default category and flow, its colour. */
export default function PageSettings({ catalog, content, title, slug, home, host, accountSlug, categories, onTitle, onSlug, onChange, onMakeHome, busy, errors }: Props) {
    const settings = content.settings;
    const { me } = useAuth();
    // The flow its people enter: only while the consultant has flows on.
    const flows = useApi(() => ((me?.account?.features ?? []).includes('flows') ? api.get<Get<'/api/admin/flows/all'>>('/api/admin/flows/all') : Promise.resolve(null)), []);
    const set = (change: Partial<PageContent['settings']>) => onChange({ ...content, settings: { ...settings, ...change } });

    return (
        <>
            <TabIntro>{t('pageEditor.settingsIntro')}</TabIntro>
            <Field label={t('pages.title')} error={errors.title} hint={t('pageEditor.titleHint')}>
                <input value={title} onChange={(event) => onTitle(event.target.value)} />
            </Field>
            <Field label={t('pages.slug')} error={errors.slug} hint={home ? t('pageEditor.homeAddress', { url: `${host}/${accountSlug}` }) : t('pageEditor.address', { url: `${host}/${accountSlug}/${slug || '…'}` })}>
                <input value={slug} onChange={(event) => onSlug(event.target.value.toLowerCase())} />
            </Field>
            <Field label={t('pageEditor.home')}>
                {home ? (
                    <p className="muted small">{t('pageEditor.isHome')}</p>
                ) : (
                    <div>
                        <ActionButton action="setup" busy={busy} onClick={onMakeHome}>
                            {t('pageEditor.makeHome')}
                        </ActionButton>
                    </div>
                )}
            </Field>
            <Field label={t('pageEditor.defaultCategory')} error={errors['settings.defaultCategoryId']} hint={t('pageEditor.defaultCategoryHint')} optional>
                <select value={settings.defaultCategoryId ?? ''} onChange={(event) => set({ defaultCategoryId: event.target.value || null })}>
                    <option value="">{t('pageEditor.noCategory')}</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                            {category.active ? '' : ` (${t('common.inactive')})`}
                        </option>
                    ))}
                </select>
            </Field>
            {flows.data && (
                <Field label={t('pageEditor.flow')} error={errors['settings.flowId']} hint={t('pageEditor.flowHint')} optional>
                    <select value={settings.flowId ?? ''} onChange={(event) => set({ flowId: event.target.value || null })}>
                        <option value="">{t('pageEditor.noFlow')}</option>
                        {(flows.data?.items ?? [])
                            .filter((flow) => flow.active || flow.id === settings.flowId)
                            .map((flow) => (
                                <option key={flow.id} value={flow.id}>
                                    {flow.name}
                                    {flow.active ? '' : ` (${t('common.inactive')})`}
                                </option>
                            ))}
                    </select>
                </Field>
            )}
            <fieldset className="accent-picker">
                <legend>{t('pageEditor.accent')}</legend>
                {catalog.accents.map((accent) => (
                    <label key={accent.key} className={`accent-swatch${settings.accent === accent.key ? ' is-selected' : ''}`}>
                        <input type="radio" name="accent" value={accent.key} checked={settings.accent === accent.key} onChange={() => set({ accent: accent.key })} />
                        {/* The page's own colours are content, not UI: shown as they will be on the page. */}
                        <span className="accent-dot" style={{ background: accent.color }} aria-hidden="true" />
                        <span>{t(`pageEditor.accents.${accent.key}`)}</span>
                    </label>
                ))}
            </fieldset>
        </>
    );
}
