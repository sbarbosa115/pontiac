import React from 'react';
import { t } from '../../lib/i18n';
import { PageHeader } from '../../components/ui';
import EmailsList from './EmailsList';

/** Plataforma › Correos. */
export default function EmailsPage() {
    return (
        <>
            <PageHeader title={t('nav.emails')} subtitle={t('emails.subtitle')} />
            <EmailsList />
        </>
    );
}
