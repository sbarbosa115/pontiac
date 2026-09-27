import React, { type FormEvent, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api, validationError } from '../lib/api';
import { useAuth } from '../lib/auth';
import { useSubmit } from '../lib/hooks';
import { t } from '../lib/i18n';
import type { Schema } from '../lib/types';
import AuthCard from '../components/AuthCard';
import { Alert, Button, Field } from '../components/ui';
import { MIN_PASSWORD_LENGTH } from '../lib/validation';

/** The emailed link of "¿Olvidaste tu contraseña?" (/restablecer?token=…): a new password, then the right sign-in. */
export default function ResetPasswordPage() {
    const [params] = useSearchParams();
    const token = params.get('token') || '';
    const navigate = useNavigate();
    const { logout } = useAuth();
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const submit = useSubmit();

    const onSubmit = async (event: FormEvent) => {
        event.preventDefault();
        const result = await submit.run(async () => {
            if (password.length < MIN_PASSWORD_LENGTH) {
                throw validationError({ password: t('invitation.passwordTooShort', { min: MIN_PASSWORD_LENGTH }) });
            }
            if (password !== confirmation) {
                throw validationError({ confirmation: t('invitation.passwordMismatch') });
            }
            return api.publicPost<Schema<'PasswordResetOutput'>>('/api/password-reset/confirm', { token, password });
        });
        if (result.ok) {
            logout();
            navigate(result.value.loginPath, { replace: true, state: { notice: t('reset.done') } });
        }
    };

    const expired = (submit.formError && !submit.errors.password && !submit.errors.confirmation) || !token;

    return (
        <AuthCard>
            <h1>{t('reset.title')}</h1>
            {expired ? (
                <>
                    <Alert kind="error">{t('reset.expired')}</Alert>
                    <p className="small">
                        <Link to="/olvide">{t('reset.askAgain')}</Link>
                    </p>
                </>
            ) : (
                <form onSubmit={onSubmit} noValidate>
                    <Field label={t('reset.password')} error={submit.errors.password} hint={t('invitation.passwordHint', { min: MIN_PASSWORD_LENGTH })}>
                        <input type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} autoFocus />
                    </Field>
                    <Field label={t('invitation.confirmPassword')} error={submit.errors.confirmation}>
                        <input type="password" autoComplete="new-password" value={confirmation} onChange={(e) => setConfirmation(e.target.value)} />
                    </Field>
                    <Button type="submit" busy={submit.busy} className="btn-block">
                        {t('reset.submit')}
                    </Button>
                </form>
            )}
        </AuthCard>
    );
}
