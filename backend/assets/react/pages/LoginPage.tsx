import React, { type FormEvent, useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { homePathFor, ROLE_CLIENT, useAuth } from '../lib/auth';
import { errorMessage, t } from '../lib/i18n';
import AuthCard from '../components/AuthCard';
import { Alert, Button, Field } from '../components/ui';

/** What another screen hands the sign-in: where to go back to, or the email of an accepted invitation. */
export interface LoginState {
    from?: string;
    email?: string;
    notice?: string;
}

/**
 * The staff door: super admins, consultants and their assistants, with email and password. Clients sign in at
 * their consultant's portal instead (PortalLoginPage).
 */
export default function LoginPage() {
    const { token, roles, claims, loading, login } = useAuth();
    const navigate = useNavigate();
    const state = useLocation().state as LoginState | null;
    const [email, setEmail] = useState(state?.email ?? '');
    const [password, setPassword] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (token && !loading && !roles.includes(ROLE_CLIENT)) {
        return <Navigate to={homePathFor(roles, claims?.accountSlug)} replace />;
    }

    const onSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setBusy(true);
        setError(null);
        try {
            const signedIn = await login(email, password);
            const home = homePathFor(signedIn?.roles, signedIn?.accountSlug);
            navigate(state?.from?.startsWith(home) ? state.from : home, { replace: true });
        } catch (err) {
            setError(errorMessage(err));
            setBusy(false);
        }
    };

    return (
        <AuthCard subtitle={t('login.staffSubtitle')}>
            <h1>{t('login.title')}</h1>
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
            <p className="muted small">{t('login.clientHint')}</p>
        </AuthCard>
    );
}
