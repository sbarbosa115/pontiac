import React, { useState } from 'react';
import { api } from '../../lib/api';
import { useApi, useSubmit } from '../../lib/hooks';
import { errorMessage, t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { ActionButton, Alert, Badge, Button, ErrorState, Field, Loading, TabIntro } from '../../components/ui';

type Settings = Schema<'WompiSettingsOutput'>;

const SECRETS = [
    ['privateKey', 'privateKeyEnding'],
    ['eventsSecret', 'eventsSecretEnding'],
    ['integritySecret', 'integritySecretEnding'],
] as const;

/** Ajustes › Pagos Wompi (owner): the merchant's keys. Secrets are written, never shown again. */
export default function WompiSettingsPage() {
    const loaded = useApi(() => api.get<Settings>('/api/admin/wompi'), []);

    if (loaded.error) return <ErrorState error={loaded.error} onRetry={loaded.reload} />;
    if (!loaded.data) return <Loading />;

    return <Form initial={loaded.data} />;
}

function Form({ initial }: { initial: Settings }) {
    const [settings, setSettings] = useState(initial);
    const [values, setValues] = useState({ publicKey: initial.publicKey, privateKey: '', eventsSecret: '', integritySecret: '' });
    const [saved, setSaved] = useState(false);
    const [test, setTest] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);
    const [testing, setTesting] = useState(false);
    const submit = useSubmit();

    const save = async () => {
        setSaved(false);
        setTest(null);
        const result = await submit.run(() => api.put<Settings>('/api/admin/wompi', values));
        if (result.ok) {
            setSettings(result.value);
            setValues({ publicKey: result.value.publicKey, privateKey: '', eventsSecret: '', integritySecret: '' });
            setSaved(true);
        }
    };

    const probe = async () => {
        setTesting(true);
        setTest(null);
        try {
            const answer = await api.post<Schema<'WompiTestOutput'>>('/api/admin/wompi/test');
            setTest({ kind: 'success', text: t('wompi.testOk', { name: answer.merchantName, mode: t(`wompi.mode.${answer.mode}`) }) });
        } catch (err) {
            setTest({ kind: 'error', text: errorMessage(err) });
        } finally {
            setTesting(false);
        }
    };

    const copyUrl = async () => {
        try {
            await navigator.clipboard.writeText(settings.eventsUrl);
            setTest({ kind: 'success', text: t('wompi.urlCopied') });
        } catch {
            setTest({ kind: 'error', text: t('wompi.copyByHand') });
        }
    };

    return (
        <form
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                save();
            }}
        >
            <TabIntro>{t('wompi.intro')}</TabIntro>
            <Alert kind="error">{submit.formError}</Alert>
            {saved && (
                <Alert kind="success" onDismiss={() => setSaved(false)}>
                    {t('wompi.saved')}
                </Alert>
            )}
            {test && (
                <Alert kind={test.kind} onDismiss={() => setTest(null)}>
                    {test.text}
                </Alert>
            )}
            <section className="card">
                <p>
                    {settings.mode ? <Badge tone={settings.mode === 'production' ? 'success' : 'warning'}>{t(`wompi.mode.${settings.mode}`)}</Badge> : <Badge tone="neutral">{t('wompi.notConfigured')}</Badge>}{' '}
                    <span className="small muted">{settings.configured ? t('wompi.ready') : t('wompi.missing')}</span>
                </p>
                <div className="form-grid">
                    <Field className="span-2" label={t('wompi.publicKey')} error={submit.errors.publicKey} hint={t('wompi.publicKeyHint')}>
                        <input value={values.publicKey} autoComplete="off" spellCheck={false} placeholder="pub_test_…" onChange={(event) => setValues({ ...values, publicKey: event.target.value })} />
                    </Field>
                    {SECRETS.map(([name, ending]) => (
                        <Field key={name} className="span-2" label={t(`wompi.${name}`)} error={submit.errors[name]} optional={name === 'privateKey'} hint={settings[ending] ? t('wompi.stored', { ending: settings[ending] ?? '' }) : t(`wompi.${name}Hint`)}>
                            <input type="password" value={values[name]} autoComplete="new-password" spellCheck={false} placeholder={settings[ending] ? `••••${settings[ending]}` : ''} onChange={(event) => setValues({ ...values, [name]: event.target.value })} />
                        </Field>
                    ))}
                </div>
            </section>
            <section className="card">
                <h2 className="section-title">{t('wompi.eventsUrl')}</h2>
                <p className="small muted">{t('wompi.eventsUrlHint')}</p>
                <p className="copy-line">
                    <code>{settings.eventsUrl}</code>
                    <ActionButton action="open" onClick={copyUrl}>
                        {t('wompi.copy')}
                    </ActionButton>
                </p>
            </section>
            <div className="form-actions">
                <ActionButton action="setup" size="md" busy={testing} disabled={!settings.mode} onClick={probe}>
                    {t('wompi.test')}
                </ActionButton>
                <Button type="submit" busy={submit.busy}>
                    {t('common.save')}
                </Button>
            </div>
        </form>
    );
}
