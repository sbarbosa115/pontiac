import React, { useState } from 'react';
import { useLocaleSettings } from '../../../lib/auth';
import { formatDateTime } from '../../../lib/format';
import { t } from '../../../lib/i18n';
import type { Schema } from '../../../lib/types';
import { BookSessionModal, useSessionActions } from '../../../components/SessionModals';
import { Actions, Alert, Button, DataTable, EmptyState, Row, RowLegend, TabIntro } from '../../../components/ui';
import { SESSION_STATUSES } from '../../../lib/agenda';

type Contact = Schema<'ContactDetailOutput'>;

/** A contact's Sesiones: every session, its notes and actions, and "Agendar sesión". */
export default function ContactSessions({ contact, onChanged }: { contact: Contact; onChanged: () => void }) {
    const { locale, timezone } = useLocaleSettings();
    const [booking, setBooking] = useState(false);
    const actions = useSessionActions(onChanged);
    const bookButton = contact.anonymized ? null : <Button onClick={() => setBooking(true)}>{t('agenda.book')}</Button>;

    return (
        <>
            <TabIntro action={bookButton}>{t('contacts.sessionsIntro')}</TabIntro>
            <Alert kind="error" onDismiss={actions.clearError}>
                {actions.error}
            </Alert>
            {contact.sessions.length === 0 ? (
                <EmptyState action={bookButton}>{t('contacts.noSessions')}</EmptyState>
            ) : (
                <>
                    <RowLegend statuses={SESSION_STATUSES.map((status) => ({ value: status, label: t(`agenda.statusName.${status}`) }))} />
                    <DataTable
                        columns={[t('agenda.startsAt'), t('agenda.plan'), t('agenda.bookedBy')]}
                        rows={contact.sessions}
                        renderRow={(session) => (
                            <Row key={session.id} status={session.status} label={t(`agenda.statusName.${session.status}`)}>
                                <td className="strong">{formatDateTime(session.startsAt, locale, timezone)}</td>
                                <td>
                                    {session.planName}
                                    {session.cancelReason && <div className="small muted">{session.cancelReason}</div>}
                                </td>
                                <td>{t(`agenda.bookedByName.${session.bookedBy}`)}</td>
                                <Actions>{actions.buttons(session, { withContact: false })}</Actions>
                            </Row>
                        )}
                    />
                </>
            )}
            {actions.modals}
            {booking && (
                <BookSessionModal
                    contact={contact}
                    onClose={() => setBooking(false)}
                    onBooked={() => {
                        setBooking(false);
                        onChanged();
                    }}
                />
            )}
        </>
    );
}
