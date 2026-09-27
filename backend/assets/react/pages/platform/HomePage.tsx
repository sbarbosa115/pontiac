import React from 'react';
import { useAuth } from '../../lib/auth';
import { t } from '../../lib/i18n';
import { PageHeader } from '../../components/ui';

/** Where a super admin lands. The consultants, their limits and the platform's settings come in milestone 0b. */
export default function HomePage() {
    const { me } = useAuth();

    return (
        <>
            <PageHeader title={t('platformHome.title', { name: me?.fullName ?? '' })} subtitle={t('platformHome.subtitle')} />
            <section className="card">
                <p className="muted">{t('platformHome.actAsHint')}</p>
            </section>
        </>
    );
}
