import React from 'react';
import { api } from '../../lib/api';
import { useLocaleSettings } from '../../lib/auth';
import { formatDateTime } from '../../lib/format';
import { useApi } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import type { Get, Schema } from '../../lib/types';
import { EmptyState, ErrorState, Loading, PageHeader } from '../../components/ui';

type Note = Schema<'PortalNoteOutput'>;

/** Notas: what the consultant shared, by session, the latest session first. */
export default function NotesPage() {
    const { locale, timezone } = useLocaleSettings();
    const notes = useApi(() => api.get<Get<'/api/portal/notes'>>('/api/portal/notes'), []);

    if (notes.error) return <ErrorState error={notes.error} onRetry={notes.reload} />;
    if (!notes.data) return <Loading />;

    const bySession = new Map<string, Note[]>();
    for (const note of notes.data.items) {
        bySession.set(note.sessionId, [...(bySession.get(note.sessionId) ?? []), note]);
    }

    return (
        <>
            <PageHeader title={t('portal.notes')} subtitle={t('portal.notesSubtitle')} />
            {bySession.size === 0 && <EmptyState>{t('portal.noNotes')}</EmptyState>}
            {[...bySession.values()].map((group) => (
                <section key={group[0]?.sessionId} className="card">
                    <h2 className="section-title">{t('portal.noteSession', { when: formatDateTime(group[0]?.sessionStartsAt ?? '', locale, timezone), plan: group[0]?.planName ?? '' })}</h2>
                    {group.map((note) => (
                        <div key={note.id} className="note">
                            <p className="small muted">
                                {note.author} · {formatDateTime(note.createdAt, locale, timezone)}
                            </p>
                            <p className="note-body">{note.body}</p>
                        </div>
                    ))}
                </section>
            ))}
        </>
    );
}
