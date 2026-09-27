import React from 'react';
import { useAuth } from '../../lib/auth';
import { t } from '../../lib/i18n';
import { PageHeader } from '../../components/ui';

/** Where a client lands in their consultant's portal. Sessions, plans, notes and files come in milestone 4. */
export default function HomePage() {
    const { me } = useAuth();

    return (
        <>
            <PageHeader title={t('portalHome.title', { name: me?.fullName ?? '' })} subtitle={me?.account?.name} />
            <section className="card">
                <p className="muted">{t('portalHome.soon')}</p>
            </section>
        </>
    );
}
