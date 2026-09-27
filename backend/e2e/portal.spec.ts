import { expect, test } from '@playwright/test';

// The demo client (app:seed-demo): Carlos paid Plan A, had a session with notes, and has another booked.
const PASSWORD = process.env.E2E_PASSWORD ?? 'demo-password-123';
const MAILPIT = process.env.MAILPIT_URL ?? 'http://localhost:8025';

test('a client signs in at the portal and finds their session, plan and shared notes', async ({ page }) => {
    await page.goto('/finanzas-claras/portal/ingresar');
    await page.getByLabel('Correo electrónico').fill('cliente@pontiac.test');
    await page.getByLabel('Contraseña').fill(PASSWORD);
    await page.getByRole('button', { name: 'Ingresar' }).click();

    await expect(page.getByRole('heading', { name: 'Tu próxima sesión' })).toBeVisible();
    await expect(page.getByText(/de 2 sesiones realizadas/)).toBeVisible();

    await page.getByRole('link', { name: 'Notas', exact: true }).click();
    await expect(page.getByText(/Registrar todos los gastos de una semana/)).toBeVisible();
    // The consultant's private note never reaches the portal.
    await expect(page.getByText(/bola de nieve/)).toHaveCount(0);

    await page.getByRole('link', { name: 'Mis planes', exact: true }).click();
    await expect(page.getByRole('row').filter({ hasText: 'Plan A' }).first()).toHaveAttribute('title', 'Activo');
});

test('a client who forgot their password gets a link and sets it again', async ({ page, request }) => {
    const since = new Date();
    await page.goto('/finanzas-claras/portal/ingresar');
    await page.getByRole('link', { name: '¿Olvidaste tu contraseña?' }).click();
    await page.getByLabel('Correo electrónico').fill('cliente@pontiac.test');
    await page.getByRole('button', { name: 'Enviarme el enlace' }).click();
    await expect(page.getByText(/te llegará un correo con el enlace/)).toBeVisible();

    let link = '';
    await expect(async () => {
        const found = await (await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent('to:cliente@pontiac.test subject:contraseña')}`)).json();
        const latest = found.messages.find((m: { Created: string }) => new Date(m.Created) >= new Date(since.getTime() - 2000));
        expect(latest).toBeTruthy();
        const message = await (await request.get(`${MAILPIT}/api/v1/message/${latest.ID}`)).json();
        link = /\/restablecer\?token=[\w-]+/.exec(message.Text)?.[0] ?? '';
        expect(link).not.toBe('');
    }).toPass({ timeout: 20_000 });

    await page.goto(link);
    await page.getByLabel('Contraseña nueva').fill(PASSWORD);
    await page.getByLabel('Repite la contraseña').fill(PASSWORD);
    await page.getByRole('button', { name: 'Guardar contraseña' }).click();
    await expect(page).toHaveURL(/\/finanzas-claras\/portal\/ingresar$/);
    await expect(page.getByText('Tu contraseña cambió. Ingresa con la nueva.')).toBeVisible();
});
