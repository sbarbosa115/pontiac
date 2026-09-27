import React, { useState } from 'react';
import { api } from '../lib/api';
import type { Session } from '../lib/agenda';
import { ROLE_OWNER, useAuth, useLocaleSettings } from '../lib/auth';
import { formatDateTime } from '../lib/format';
import { useApi, useSubmit } from '../lib/hooks';
import { t } from '../lib/i18n';
import type { Get, Schema } from '../lib/types';
import { ActionButton, Badge, Button, ErrorState, Field, Loading, Modal } from './ui';

type Note = Schema<'SessionNoteOutput'>;

/**
 * A session's notes: read them, add one, change one's text. Private notes are the owner's alone — an assistant only
 * sees and writes shared ones (the client will read those in the portal).
 */
export function SessionNotesModal({ session, onClose }: { session: Session; onClose: () => void }) {
    const { roles } = useAuth();
    const { locale, timezone } = useLocaleSettings();
    const isOwner = roles.includes(ROLE_OWNER);
    const notes = useApi(() => api.get<Get<'/api/admin/sessions/{id}/notes'>>(`/api/admin/sessions/${session.id}/notes`), [session.id]);
    const [editing, setEditing] = useState<Note | null>(null);

    return (
        <Modal title={t('notes.title', { name: session.contact.fullName })} onClose={onClose} size="lg">
            <p className="small muted">
                {formatDateTime(session.startsAt, locale, timezone)} · {session.planName}
            </p>
            {notes.error ? (
                <ErrorState error={notes.error} onRetry={notes.reload} />
            ) : !notes.data ? (
                <Loading />
            ) : notes.data.items.length === 0 ? (
                <p className="muted">{t('notes.empty')}</p>
            ) : (
                <ul className="note-list">
                    {notes.data.items.map((note) =>
                        editing?.id === note.id ? (
                            <li key={note.id} className="note">
                                <NoteForm
                                    note={note}
                                    isOwner={isOwner}
                                    path={`/api/admin/session-notes/${note.id}`}
                                    method="put"
                                    onCancel={() => setEditing(null)}
                                    onSaved={() => {
                                        setEditing(null);
                                        notes.reload();
                                    }}
                                />
                            </li>
                        ) : (
                            <li key={note.id} className="note">
                                <div className="note-meta small muted">
                                    <Badge tone={note.visibility === 'private' ? 'warning' : 'info'}>{t(`notes.visibility.${note.visibility}`)}</Badge>
                                    <span>
                                        {note.author.fullName} · {formatDateTime(note.createdAt, locale, timezone)}
                                    </span>
                                    {note.editable && (
                                        <ActionButton action="edit" onClick={() => setEditing(note)}>
                                            {t('common.edit')}
                                        </ActionButton>
                                    )}
                                </div>
                                <p className="note-body">{note.body}</p>
                            </li>
                        ),
                    )}
                </ul>
            )}
            <h3 className="section-title">{t('notes.add')}</h3>
            <NoteForm isOwner={isOwner} path={`/api/admin/sessions/${session.id}/notes`} method="post" onSaved={notes.reload} />
        </Modal>
    );
}

function NoteForm({ note, isOwner, path, method, onSaved, onCancel }: { note?: Note; isOwner: boolean; path: string; method: 'post' | 'put'; onSaved: () => void; onCancel?: () => void }) {
    // Financial details are sensitive: the owner's notes start private.
    const [body, setBody] = useState(note?.body ?? '');
    const [visibility, setVisibility] = useState(note?.visibility ?? (isOwner ? 'private' : 'shared'));
    const submit = useSubmit();

    const save = async () => {
        const result = await submit.run(() => (method === 'post' ? api.post(path, { body, visibility }) : api.put(path, { body, visibility })));
        if (result.ok) {
            if (method === 'post') setBody('');
            onSaved();
        }
    };

    return (
        <form
            noValidate
            className="note-form"
            onSubmit={(event) => {
                event.preventDefault();
                save();
            }}
        >
            <Field label={t('notes.body')} error={submit.errors.body ?? submit.formError ?? undefined}>
                <textarea value={body} rows={4} maxLength={5000} onChange={(event) => setBody(event.target.value)} />
            </Field>
            <div className="note-form-actions">
                {isOwner ? (
                    <label className="filter-select">
                        <span className="filter-select-label">{t('notes.whoReads')}</span>
                        <select value={visibility} onChange={(event) => setVisibility(event.target.value)}>
                            <option value="private">{t('notes.visibility.private')}</option>
                            <option value="shared">{t('notes.visibility.shared')}</option>
                        </select>
                    </label>
                ) : (
                    <span className="small muted">{t('notes.sharedOnly')}</span>
                )}
                {onCancel && (
                    <Button variant="ghost" onClick={onCancel}>
                        {t('common.cancel')}
                    </Button>
                )}
                <Button type="submit" busy={submit.busy}>
                    {method === 'post' ? t('notes.save') : t('common.save')}
                </Button>
            </div>
        </form>
    );
}
