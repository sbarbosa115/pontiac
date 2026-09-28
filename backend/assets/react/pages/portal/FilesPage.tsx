import React, { useState } from 'react';
import { api, downloadFile } from '../../lib/api';
import { useLocaleSettings } from '../../lib/auth';
import { formatDateTime, formatFileSize } from '../../lib/format';
import { useApi } from '../../lib/hooks';
import { rowErrorMessage, t } from '../../lib/i18n';
import { FILE_ACCEPT } from '../../lib/portal';
import type { Get, Schema } from '../../lib/types';
import { ActionButton, Actions, Alert, DataTable, EmptyState, ErrorState, Loading, PageHeader, TabIntro, UploadButton } from '../../components/ui';

type File = Schema<'ClientFileOutput'>;

/** Archivos: what the consultant shared with them, and what they uploaded. */
export default function FilesPage() {
    const { locale, timezone } = useLocaleSettings();
    const files = useApi(() => api.get<Get<'/api/portal/files'>>('/api/portal/files'), []);
    const [uploading, setUploading] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    if (files.error) return <ErrorState error={files.error} onRetry={files.reload} />;
    if (!files.data) return <Loading />;

    const upload = async (chosen: globalThis.File[]) => {
        setUploading(true);
        setError(null);
        setNotice(null);
        try {
            for (const file of chosen) {
                const form = new FormData();
                form.append('file', file);
                await api.upload('/api/portal/files', form);
            }
            setNotice(t('files.uploaded'));
            files.reload();
        } catch (err) {
            setError(rowErrorMessage(err));
        } finally {
            setUploading(false);
        }
    };

    const download = (file: File) => downloadFile(`/api/portal/files/${file.id}/download`, file.name).catch((err: unknown) => setError(rowErrorMessage(err)));
    const uploadButton = <UploadButton accept={FILE_ACCEPT} multiple label={t('files.upload')} busy={uploading} onFiles={upload} />;

    return (
        <>
            <PageHeader title={t('portal.files')} subtitle={t('portal.filesSubtitle')} />
            <TabIntro action={files.data.items.length > 0 ? uploadButton : null}>{t('portal.filesIntro')}</TabIntro>
            <Alert kind="success" onDismiss={() => setNotice(null)}>
                {notice}
            </Alert>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            {files.data.items.length === 0 ? (
                <EmptyState action={uploadButton}>{t('portal.noFiles')}</EmptyState>
            ) : (
                <DataTable
                    columns={[t('files.name'), t('files.from'), t('files.date'), t('files.size')]}
                    rows={files.data.items}
                    renderRow={(file: File) => (
                        <tr key={file.id}>
                            <td className="strong">{file.name}</td>
                            <td>{file.byClient ? t('files.byYou') : file.uploadedBy}</td>
                            <td>{formatDateTime(file.createdAt, locale, timezone)}</td>
                            <td>{formatFileSize(file.sizeBytes, locale)}</td>
                            <Actions>
                                <ActionButton action="file" onClick={() => download(file)}>
                                    {t('files.download')}
                                </ActionButton>
                            </Actions>
                        </tr>
                    )}
                />
            )}
        </>
    );
}
