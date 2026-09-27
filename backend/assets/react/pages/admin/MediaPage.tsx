import React, { useState } from 'react';
import { api } from '../../lib/api';
import { useLocaleSettings } from '../../lib/auth';
import { formatFileSize } from '../../lib/format';
import { useForm, useList, useSubmit } from '../../lib/hooks';
import { errorMessage, t } from '../../lib/i18n';
import { type MediaAsset, uploadImage } from '../../components/MediaPicker';
import { Actions, Alert, Field, FilterBar, FormModal, IconButton, ListView, Row, RowLegend, TabIntro, UploadButton } from '../../components/ui';

/** Ajustes › Medios: the images the consultant's pages use. */
export default function MediaPage() {
    const { locale } = useLocaleSettings();
    const list = useList<MediaAsset>('/api/admin/media', { q: '', includeInactive: '' });
    const [editing, setEditing] = useState<MediaAsset | null>(null);
    const [uploading, setUploading] = useState(false);
    const [busyId, setBusyId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const upload = async (files: File[]) => {
        setUploading(true);
        setError(null);
        try {
            for (const file of files) {
                await uploadImage(file);
            }
            list.reload();
        } catch (err) {
            const violation = (err as { violations?: { message: string }[] }).violations?.[0]?.message;
            setError(violation ?? errorMessage(err));
        } finally {
            setUploading(false);
        }
    };

    const toggle = async (asset: MediaAsset) => {
        setBusyId(asset.id);
        setError(null);
        try {
            await (asset.active ? api.del(`/api/admin/media/${asset.id}`) : api.post(`/api/admin/media/${asset.id}/enable`));
            list.reload();
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusyId(null);
        }
    };

    const uploadButton = <UploadButton accept="image/jpeg,image/png,image/webp" multiple label={t('media.upload')} busy={uploading} onFiles={upload} />;

    return (
        <>
            <TabIntro>{t('media.intro')}</TabIntro>
            <FilterBar
                search={list.filters.q}
                onSearch={(q) => list.update({ q })}
                searchPlaceholder={t('media.searchPlaceholder')}
                filters={[
                    {
                        name: 'status',
                        label: t('media.status'),
                        value: list.filters.includeInactive ? 'all' : 'active',
                        onChange: (value) => list.update({ includeInactive: value === 'all' ? '1' : '' }),
                        options: [
                            { value: 'active', label: t('media.onlyActive') },
                            { value: 'all', label: t('media.all') },
                        ],
                    },
                ]}
            >
                {uploadButton}
            </FilterBar>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            <RowLegend statuses={[{ value: 'active', label: t('media.active') }, { value: 'inactive', label: t('media.inactive') }]} />
            <ListView
                list={list}
                empty={t('media.empty')}
                showAll={{ q: '', includeInactive: '' }}
                emptyAll={t('media.emptyAll')}
                emptyAction={uploadButton}
                columns={[t('media.image'), t('media.name'), t('media.altText'), t('media.size')]}
                renderRow={(asset) => (
                    <Row key={asset.id} status={asset.active ? 'active' : 'inactive'} label={asset.active ? t('media.active') : t('media.inactive')} muted={!asset.active}>
                        <td>
                            <img className="media-thumb" src={asset.thumbUrl} alt="" loading="lazy" width={72} height={Math.round((72 * asset.height) / asset.width)} />
                        </td>
                        <td>
                            <div>{asset.originalName}</div>
                            <div className="small muted">
                                {asset.width} × {asset.height}
                            </div>
                        </td>
                        <td>{asset.altText || <span className="text-danger small">{t('media.noAlt')}</span>}</td>
                        <td className="nowrap">{formatFileSize(asset.sizeBytes, locale)}</td>
                        <Actions>
                            <IconButton icon="pencil" label={t('common.edit')} onClick={() => setEditing(asset)} />
                            <IconButton icon={asset.active ? 'ban' : 'check'} label={asset.active ? t('common.disable') : t('common.enable')} busy={busyId === asset.id} onClick={() => toggle(asset)} />
                        </Actions>
                    </Row>
                )}
            />
            {editing && (
                <AltTextForm
                    asset={editing}
                    onClose={() => setEditing(null)}
                    onSaved={() => {
                        setEditing(null);
                        list.reload();
                    }}
                />
            )}
        </>
    );
}

function AltTextForm({ asset, onClose, onSaved }: { asset: MediaAsset; onClose: () => void; onSaved: () => void }) {
    const form = useForm({ altText: asset.altText });
    const submit = useSubmit();

    const save = async () => {
        const result = await submit.run(() => api.patch(`/api/admin/media/${asset.id}`, form.values));
        if (result.ok) onSaved();
    };

    return (
        <FormModal title={t('media.edit')} onClose={onClose} onSubmit={save} submit={submit}>
            <img className="media-preview" src={asset.thumbUrl} alt="" />
            <Field label={t('media.altText')} error={submit.errors.altText} hint={t('media.altTextHint')}>
                <input {...form.bind('altText')} autoFocus maxLength={255} />
            </Field>
        </FormModal>
    );
}
