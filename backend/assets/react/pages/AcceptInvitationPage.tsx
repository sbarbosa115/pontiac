import React, { type FormEvent, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api, ApiError, validationError } from '../lib/api';
import { portalPath, useAuth } from '../lib/auth';
import { MIN_PASSWORD_LENGTH } from '../lib/validation';
import { useApi, useSubmit } from '../lib/hooks';
import { t } from '../lib/i18n';
import type { Schema } from '../lib/types';
import AuthCard from '../components/AuthCard';
import { Alert, Button, Field, Loading } from '../components/ui';


/** Where someone signs in once their password is set: a client at their consultant's portal, everyone else at /login. */
function signInPath(invitation: Schema<'InvitationOutput'>): string {
    return invitation.role === 'client' ? `${portalPath(invitation.accountSlug)}/ingresar` : '/login';
}

/** /invitacion?token=…: the invited person sets their own password. */
export default function AcceptInvitationPage() {
    const [params] = useSearchParams();
    const token = params.get('token') || '';
    const navigate = useNavigate();
    const { logout } = useAuth();
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const submit = useSubmit();

    const invitation = useApi(
        () => (token ? api.publicPost<Schema<'InvitationOutput'>>('/api/invitations/lookup', { token }) : Promise.reject(new ApiError(404, null))),
        [token],
    );

    const onSubmit = async (event: FormEvent) => {
        event.preventDefault();
        const result = await submit.run(async () => {
            if (password.length < MIN_PASSWORD_LENGTH) {
                throw validationError({ password: t('invitation.passwordTooShort', { min: MIN_PASSWORD_LENGTH }) });
            }
            if (password !== confirmation) {
                throw validationError({ confirmation: t('invitation.passwordMismatch') });
            }
            return api.publicPost('/api/invitations/accept', { token, password });
        });
        if (result.ok && invitation.data) {
            // Whoever was signed in on this browser is not the person who just set a password: without this the
            // sign-in page would send them straight back into that other session.
            logout();
            navigate(signInPath(invitation.data), { replace: true, state: { notice: t('invitation.done'), email: invitation.data.email } });
        }
    };

    if (invitation.loading && !invitation.data) {
        return (
            <AuthCard>
                <Loading />
            </AuthCard>
        );
    }

    if (invitation.error || !invitation.data) {
        return (
            <AuthCard>
                <h1>{t('invitation.invalidTitle')}</h1>
                <p className="muted">{t('invitation.invalidBody')}</p>
                <Link to="/login" className="btn btn-ghost btn-block">
                    {t('invitation.goToLogin')}
                </Link>
            </AuthCard>
        );
    }

    const { fullName, email, accountName } = invitation.data;
    return (
        <AuthCard subtitle={accountName}>
            <form onSubmit={onSubmit} noValidate>
                <h1>{t('invitation.title', { name: fullName })}</h1>
                <p className="muted">{t('invitation.subtitle')}</p>
                <Alert kind="error">{submit.formError}</Alert>
                <Field label={t('login.email')}>
                    <input type="email" value={email} readOnly autoComplete="username" />
                </Field>
                <Field label={t('invitation.password')} error={submit.errors.password} hint={t('invitation.passwordHint', { min: MIN_PASSWORD_LENGTH })}>
                    <input type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} />
                </Field>
                <Field label={t('invitation.confirmPassword')} error={submit.errors.confirmation}>
                    <input type="password" autoComplete="new-password" value={confirmation} onChange={(e) => setConfirmation(e.target.value)} />
                </Field>
                <Button type="submit" busy={submit.busy} className="btn-block">
                    {t('invitation.submit')}
                </Button>
            </form>
        </AuthCard>
    );
}
