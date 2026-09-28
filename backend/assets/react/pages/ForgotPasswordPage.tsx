import React, { type FormEvent, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../lib/api';
import { portalPath } from '../lib/auth';
import { useSubmit } from '../lib/hooks';
import { t } from '../lib/i18n';
import AuthCard from '../components/AuthCard';
import { Alert, Button, Field } from '../components/ui';

/**
 * "¿Olvidaste tu contraseña?", at /olvide (staff) and /<consultant>/portal/olvide (clients). The answer is the same
 * whether the email has an account or not.
 */
export default function ForgotPasswordPage() {
    const { slug } = useParams();
    const [email, setEmail] = useState('');
    const [sent, setSent] = useState(false);
    const submit = useSubmit();
    const loginPath = slug ? `${portalPath(slug)}/ingresar` : '/login';

    const onSubmit = async (event: FormEvent) => {
        event.preventDefault();
        const result = await submit.run(() => api.publicPost('/api/password-reset/request', { email, account: slug ?? null }));
        if (result.ok) setSent(true);
    };

    return (
        <AuthCard subtitle={slug ? t('portalLogin.subtitle') : undefined}>
            <h1>{t('forgot.title')}</h1>
            {sent ? (
                <Alert kind="success">{t('forgot.sent', { email })}</Alert>
            ) : (
                <form onSubmit={onSubmit} noValidate>
                    <p className="muted small">{t('forgot.intro')}</p>
                    <Alert kind="error">{submit.formError}</Alert>
                    <Field label={t('login.email')} error={submit.errors.email}>
                        <input type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} autoFocus />
                    </Field>
                    <Button type="submit" busy={submit.busy} className="btn-block">
                        {t('forgot.submit')}
                    </Button>
                </form>
            )}
            <p className="small">
                <Link to={loginPath}>← {t('forgot.back')}</Link>
            </p>
        </AuthCard>
    );
}
