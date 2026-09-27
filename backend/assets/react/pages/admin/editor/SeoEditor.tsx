import React from 'react';
import { t } from '../../../lib/i18n';
import type { PageContent } from '../../../lib/pages';
import { ImageField } from '../../../components/MediaPicker';
import { Checkbox, Field, TabIntro } from '../../../components/ui';
import { TextField } from './SectionsEditor';

/** SEO: how the page shows in search results and when it is shared, and whether search engines may list it. */
export default function SeoEditor({ content, url, onChange, errors }: { content: PageContent; url: string; onChange: (content: PageContent) => void; errors: Record<string, string> }) {
    const seo = content.seo;
    const set = (change: Partial<PageContent['seo']>) => onChange({ ...content, seo: { ...seo, ...change } });

    return (
        <>
            <TabIntro>{t('pageEditor.seoIntro')}</TabIntro>
            <div className="search-snippet" aria-label={t('pageEditor.snippet')}>
                <span className="search-snippet-url">{url}</span>
                <span className="search-snippet-title">{seo.title || t('pageEditor.snippetNoTitle')}</span>
                <span className="search-snippet-description">{seo.description || t('pageEditor.snippetNoDescription')}</span>
            </div>
            <TextField label={t('pageEditor.seoTitle')} value={seo.title} max={70} error={errors['seo.title']} onChange={(title) => set({ title })} />
            <TextField label={t('pageEditor.seoDescription')} value={seo.description} max={160} multiline optional error={errors['seo.description']} onChange={(description) => set({ description })} />
            <Field label={t('pageEditor.seoImage')} error={errors['seo.imageId']} hint={t('pageEditor.seoImageHint')} optional>
                <ImageField value={seo.imageId} onChange={(imageId) => set({ imageId })} />
            </Field>
            <Checkbox label={t('pageEditor.seoIndex')} checked={seo.index} onChange={(index) => set({ index })} />
        </>
    );
}
