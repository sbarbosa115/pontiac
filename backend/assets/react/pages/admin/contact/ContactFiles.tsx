import React, { useState } from 'react';
import { api, downloadFile } from '../../../lib/api';
import { useLocaleSettings } from '../../../lib/auth';
import { formatDateTime, formatFileSize } from '../../../lib/format';
import { useApi } from '../../../lib/hooks';
import { rowErrorMessage, t } from '../../../lib/i18n';
import { FILE_ACCEPT } from '../../../lib/portal';
import type { Get, Schema } from '../../../lib/types';
import { ActionButton, Actions, Alert, Checkbox, DataTable, EmptyState, ErrorState, IconButton, Loading, Row, RowLegend, TabIntro, UploadButton } from '../../../components/ui';

type Contact = Schema<'ContactDetailOutput'>;
type File = Schema<'ClientFileOutput'>;

/**
 * Archivos: the person's files. The team uploads them shared (the client sees them in the portal) or internal; what
 * the client uploads is always shared. Nothing is deleted: a file is turned off.
 */
export default function ContactFiles({ contact }: { contact: Contact }) {
    const { locale, timezone } = useLocaleSettings();
    const files = useApi(() => api.get<Get<'/api/admin/contacts/{id}/files'>>(`/api/admin/contacts/${contact.id}/files`), [contact.id]);
    const [shareNew, setShareNew] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [busy, setBusy] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    if (files.error) return <ErrorState error={files.error} onRetry={files.reload} />;
    if (!files.data) return <Loading />;

    const upload = async (chosen: globalThis.File[]) => {
        setUploading(true);
        setError(null);
        try {
            for (const file of chosen) {
                const form = new FormData();
                form.append('file', file);
                form.append('shared', shareNew ? '1' : '0');
                await api.upload(`/api/admin/contacts/${contact.id}/files`, form);
            }
            files.reload();
        } catch (err) {
            setError(rowErrorMessage(err));
        } finally {
            setUploading(false);
        }
    };

    const run = async (key: string, action: () => Promise<unknown>) => {
        setBusy(key);
        setError(null);
        try {
            await action();
            files.reload();
        } catch (err) {
            setError(rowErrorMessage(err));
        } finally {
            setBusy(null);
        }
    };

    const uploader = contact.anonymized ? null : (
        <div className="upload-with-option">
            <Checkbox label={t('files.shareNew')} checked={shareNew} onChange={setShareNew} />
            <UploadButton accept={FILE_ACCEPT} multiple label={t('files.upload')} busy={uploading} onFiles={upload} />
        </div>
    );
    const status = (file: File) => (!file.active ? 'inactive' : file.shared ? 'shared' : 'internal');

    return (
        <>
            <TabIntro action={files.data.items.length > 0 ? uploader : null}>{t('files.intro')}</TabIntro>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            {files.data.items.length === 0 ? (
                <EmptyState action={uploader}>{t('files.empty')}</EmptyState>
            ) : (
                <>
                    <RowLegend statuses={['shared', 'internal', 'inactive'].map((value) => ({ value, label: t(`files.status.${value}`) }))} />
                    <DataTable
                        columns={[t('files.name'), t('files.from'), t('files.date'), t('files.size')]}
                        rows={files.data.items}
                        renderRow={(file) => (
                            <Row key={file.id} status={status(file)} label={t(`files.status.${status(file)}`)} muted={!file.active}>
                                <td className="strong">{file.name}</td>
                                <td>{file.byClient ? t('files.byClient', { name: file.uploadedBy }) : file.uploadedBy}</td>
                                <td>{formatDateTime(file.createdAt, locale, timezone)}</td>
                                <td>{formatFileSize(file.sizeBytes, locale)}</td>
                                <Actions>
                                    {!file.byClient && file.active && (
                                        <ActionButton action={file.shared ? 'revert' : 'setup'} busy={busy === `${file.id}:share`} onClick={() => run(`${file.id}:share`, () => api.patch(`/api/admin/client-files/${file.id}`, { shared: !file.shared }))}>
                                            {file.shared ? t('files.unshare') : t('files.share')}
                                        </ActionButton>
                                    )}
                                    <ActionButton action="file" onClick={() => downloadFile(`/api/admin/client-files/${file.id}/download`, file.name).catch((err: unknown) => setError(rowErrorMessage(err)))}>
                                        {t('files.download')}
                                    </ActionButton>
                                    <IconButton
                                        icon={file.active ? 'ban' : 'check'}
                                        label={file.active ? t('common.disable') : t('common.enable')}
                                        busy={busy === `${file.id}:active`}
                                        onClick={() => run(`${file.id}:active`, () => (file.active ? api.del(`/api/admin/client-files/${file.id}`) : api.post(`/api/admin/client-files/${file.id}/enable`)))}
                                    />
                                </Actions>
                            </Row>
                        )}
                    />
                </>
            )}
        </>
    );
}
