import React, { useState } from 'react';
import { api } from '../../lib/api';
import { ROLE_OWNER, useAuth } from '../../lib/auth';
import { useApi, useSubmit } from '../../lib/hooks';
import { t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { Alert, Button, ErrorState, Field, Loading, TabIntro } from '../../components/ui';

type Privacy = Schema<'PrivacyOutput'>;

/** Ajustes › Privacidad: the policy the consultant's forms link to (Ley 1581). Only the owner changes it. */
export default function PrivacyPage() {
    const loaded = useApi(() => api.get<Privacy>('/api/admin/privacy'), []);
    if (loaded.error) return <ErrorState error={loaded.error} onRetry={loaded.reload} />;
    if (!loaded.data) return <Loading />;

    return <PrivacyForm initial={loaded.data} />;
}

function PrivacyForm({ initial }: { initial: Privacy }) {
    const { me, roles } = useAuth();
    const canEdit = roles.includes(ROLE_OWNER);
    const [privacy, setPrivacy] = useState(initial);
    const [text, setText] = useState(initial.text);
    const [done, setDone] = useState(false);
    const submit = useSubmit();

    const save = async (event: React.FormEvent) => {
        event.preventDefault();
        setDone(false);
        const result = await submit.run(() => api.put<Privacy>('/api/admin/privacy', { text }));
        if (result.ok) {
            setPrivacy(result.value);
            setText(result.value.text);
            setDone(true);
        }
    };

    return (
        <form onSubmit={save} noValidate className="card">
            <TabIntro>{t('privacy.intro', { url: `${window.location.host}/${me?.account?.slug ?? ''}/privacidad` })}</TabIntro>
            <Alert kind="success">{done ? t('common.saved') : null}</Alert>
            <Alert kind="error">{submit.formError}</Alert>
            {privacy.usingDefault && <Alert kind="info">{t('privacy.usingDefault')}</Alert>}
            <Field label={t('privacy.text')} error={submit.errors.text} hint={canEdit ? t('privacy.hint') : t('privacy.ownerOnly')}>
                <textarea rows={16} value={text} readOnly={!canEdit} onChange={(event) => setText(event.target.value)} />
            </Field>
            {canEdit && (
                <div className="form-actions">
                    <Button type="submit" busy={submit.busy}>
                        {t('common.save')}
                    </Button>
                </div>
            )}
        </form>
    );
}
