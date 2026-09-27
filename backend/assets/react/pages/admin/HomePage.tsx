import React from 'react';
import { useAuth } from '../../lib/auth';
import { t } from '../../lib/i18n';
import { PageHeader } from '../../components/ui';

/** Inicio: where a consultant and their assistants land. Each milestone adds what needs attention today. */
export default function HomePage() {
    const { me } = useAuth();
    const slug = me?.account?.slug ?? '';

    return (
        <>
            <PageHeader title={t('adminHome.title', { name: me?.fullName ?? '' })} subtitle={me?.account?.name} />
            <section className="card">
                <h2>{t('adminHome.publicAddress')}</h2>
                <p className="muted">{t('adminHome.publicAddressHint')}</p>
                <p>
                    <a href={`/${slug}`} target="_blank" rel="noreferrer">
                        {`${window.location.host}/${slug}`}
                    </a>
                </p>
            </section>
        </>
    );
}
