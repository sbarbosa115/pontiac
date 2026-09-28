import React, { type FormEvent, useState } from 'react';
import { api, validationError } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useSubmit } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import { MIN_PASSWORD_LENGTH } from '../../lib/validation';
import { Alert, Button, DefinitionList, Field, PageHeader } from '../../components/ui';

/** Mi cuenta: who they are here, and a new password. The theme is in the menu, as everywhere. */
export default function AccountPage() {
    const { me } = useAuth();
    const [values, setValues] = useState({ currentPassword: '', newPassword: '', confirmation: '' });
    const [saved, setSaved] = useState(false);
    const submit = useSubmit();

    const onSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setSaved(false);
        const result = await submit.run(async () => {
            if (values.newPassword.length < MIN_PASSWORD_LENGTH) {
                throw validationError({ newPassword: t('invitation.passwordTooShort', { min: MIN_PASSWORD_LENGTH }) });
            }
            if (values.newPassword !== values.confirmation) {
                throw validationError({ confirmation: t('invitation.passwordMismatch') });
            }
            return api.post('/api/me/password', { currentPassword: values.currentPassword, newPassword: values.newPassword });
        });
        if (result.ok) {
            setValues({ currentPassword: '', newPassword: '', confirmation: '' });
            setSaved(true);
        }
    };

    return (
        <>
            <PageHeader title={t('portal.account')} subtitle={me?.account?.name} />
            <section className="card">
                <DefinitionList
                    items={[
                        [t('account.name'), me?.fullName ?? ''],
                        [t('login.email'), me?.email ?? ''],
                    ]}
                />
            </section>
            <form className="card" onSubmit={onSubmit} noValidate>
                <h2 className="section-title">{t('account.changePassword')}</h2>
                {saved && (
                    <Alert kind="success" onDismiss={() => setSaved(false)}>
                        {t('account.passwordChanged')}
                    </Alert>
                )}
                <Alert kind="error">{submit.formError}</Alert>
                <div className="form-grid">
                    <Field className="span-2" label={t('account.currentPassword')} error={submit.errors.currentPassword}>
                        <input type="password" autoComplete="current-password" value={values.currentPassword} onChange={(e) => setValues({ ...values, currentPassword: e.target.value })} />
                    </Field>
                    <Field label={t('reset.password')} error={submit.errors.newPassword} hint={t('invitation.passwordHint', { min: MIN_PASSWORD_LENGTH })}>
                        <input type="password" autoComplete="new-password" value={values.newPassword} onChange={(e) => setValues({ ...values, newPassword: e.target.value })} />
                    </Field>
                    <Field label={t('invitation.confirmPassword')} error={submit.errors.confirmation}>
                        <input type="password" autoComplete="new-password" value={values.confirmation} onChange={(e) => setValues({ ...values, confirmation: e.target.value })} />
                    </Field>
                </div>
                <div className="form-actions">
                    <Button type="submit" busy={submit.busy}>
                        {t('account.changePassword')}
                    </Button>
                </div>
            </form>
        </>
    );
}
