import React from 'react';
import { Link } from 'react-router-dom';
import { t } from '../lib/i18n';
import AuthCard from '../components/AuthCard';

export default function NotFoundPage() {
    return (
        <AuthCard>
            <h1>{t('notFound.title')}</h1>
            <p className="muted">{t('notFound.body')}</p>
            <Link to="/login" className="btn btn-primary btn-block">
                {t('notFound.home')}
            </Link>
        </AuthCard>
    );
}
