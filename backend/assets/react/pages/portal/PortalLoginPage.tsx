import React, { type FormEvent, useState } from 'react';
import { Navigate, useLocation, useNavigate, useParams } from 'react-router-dom';
import { portalPath, ROLE_CLIENT, useAuth } from '../../lib/auth';
import { errorMessage, t } from '../../lib/i18n';
import AuthCard from '../../components/AuthCard';
import { Alert, Button, Field } from '../../components/ui';
import type { LoginState } from '../LoginPage';

/**
 * A client's door, at their consultant's address (/<slug>/portal/ingresar): the same email can be a client of two
 * consultants, and the address says which.
 */
export default function PortalLoginPage() {
    const { slug = '' } = useParams();
    const { token, roles, claims, loading, portalLogin } = useAuth();
    const navigate = useNavigate();
    const state = useLocation().state as LoginState | null;
    const [email, setEmail] = useState(state?.email ?? '');
    const [password, setPassword] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const home = portalPath(slug);

    if (token && !loading && roles.includes(ROLE_CLIENT) && claims?.accountSlug === slug) {
        return <Navigate to={home} replace />;
    }

    const onSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setBusy(true);
        setError(null);
        try {
            await portalLogin(slug, email, password);
            navigate(state?.from?.startsWith(home) ? state.from : home, { replace: true });
        } catch (err) {
            setError(errorMessage(err));
            setBusy(false);
        }
    };

    return (
        <AuthCard subtitle={t('portalLogin.subtitle')}>
            <h1>{t('portalLogin.title')}</h1>
            <Alert kind="success">{state?.notice}</Alert>
            <form onSubmit={onSubmit} noValidate>
                <Alert kind="error">{error}</Alert>
                <Field label={t('login.email')}>
                    <input type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} autoFocus />
                </Field>
                <Field label={t('login.password')}>
                    <input type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />
                </Field>
                <Button type="submit" busy={busy} className="btn-block">
                    {t('login.submit')}
                </Button>
            </form>
            <p className="muted small">{t('portalLogin.noAccess')}</p>
        </AuthCard>
    );
}
