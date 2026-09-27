import React, { useState } from 'react';
import { api } from '../lib/api';
import { useApi, useList } from '../lib/hooks';
import { errorMessage, t } from '../lib/i18n';
import type { Schema } from '../lib/types';
import { ActionButton, Alert, Button, EmptyState, Loading, Modal, Pager, SearchInput, UploadButton } from './ui';

export type MediaAsset = Schema<'MediaAssetOutput'>;

/** Uploads one image to the library; resolves to it, or rejects with the API's error. */
export function uploadImage(file: File): Promise<MediaAsset> {
    const form = new FormData();
    form.append('file', file);
    return api.upload<MediaAsset>('/api/admin/media', form);
}

/** Choose an image from the library, or upload one and choose it. */
export function MediaPicker({ onPick, onClose }: { onPick: (asset: MediaAsset) => void; onClose: () => void }) {
    const list = useList<MediaAsset>('/api/admin/media', { q: '', perPage: 24 });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const upload = async (files: File[]) => {
        const file = files[0];
        if (!file) return;
        setBusy(true);
        setError(null);
        try {
            onPick(await uploadImage(file));
        } catch (err) {
            const violation = (err as { violations?: { message: string }[] }).violations?.[0]?.message;
            setError(violation ?? errorMessage(err));
        } finally {
            setBusy(false);
        }
    };

    return (
        <Modal title={t('media.pick')} onClose={onClose} size="wide">
            <div className="media-picker-bar">
                <SearchInput value={list.filters.q as string} onChange={(q) => list.update({ q })} placeholder={t('media.searchPlaceholder')} />
                <UploadButton accept="image/jpeg,image/png,image/webp" label={t('media.upload')} busy={busy} onFiles={upload} />
            </div>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            {list.loading && !list.data ? <Loading /> : null}
            {list.data && list.data.items.length === 0 ? <EmptyState>{t('media.pickEmpty')}</EmptyState> : null}
            <div className="media-grid">
                {list.data?.items.map((asset) => (
                    <button key={asset.id} type="button" className="media-tile" onClick={() => onPick(asset)}>
                        <img src={asset.thumbUrl} alt={asset.altText} loading="lazy" />
                        <span className="small">{asset.altText || asset.originalName}</span>
                    </button>
                ))}
            </div>
            {list.data && <Pager data={list.data} onPage={list.setPage} />}
            <div className="form-actions">
                <Button variant="ghost" onClick={onClose}>
                    {t('common.cancel')}
                </Button>
            </div>
        </Modal>
    );
}

/** An image field of the page editor: the chosen image's thumbnail, "Elegir imagen", "Quitar". */
export function ImageField({ value, onChange }: { value: string | null; onChange: (id: string | null) => void }) {
    const [picking, setPicking] = useState(false);
    const current = useApi(() => (value ? api.get<MediaAsset>(`/api/admin/media/${value}`) : Promise.resolve(null)), [value]);

    return (
        <div className="image-field">
            {current.data ? <img src={current.data.thumbUrl} alt={current.data.altText} className="image-field-thumb" /> : <div className="image-field-empty">{t('media.none')}</div>}
            <div className="image-field-actions">
                <ActionButton action="setup" onClick={() => setPicking(true)}>
                    {value ? t('media.change') : t('media.choose')}
                </ActionButton>
                {value && (
                    <ActionButton action="danger" onClick={() => onChange(null)}>
                        {t('media.remove')}
                    </ActionButton>
                )}
            </div>
            {picking && (
                <MediaPicker
                    onClose={() => setPicking(false)}
                    onPick={(asset) => {
                        setPicking(false);
                        onChange(asset.id);
                    }}
                />
            )}
        </div>
    );
}
