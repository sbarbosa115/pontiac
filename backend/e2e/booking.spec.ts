import { expect, test } from '@playwright/test';

// The demo consultant's home page books its free diagnostic (`app:seed-demo`, README "Demo accounts").
const PASSWORD = process.env.E2E_PASSWORD ?? 'demo-password-123';
const MAILPIT = process.env.MAILPIT_URL ?? 'http://localhost:8025';

test('a visitor books a free session, cancels it from the emailed link, and the consultant sees it', async ({ page, request }) => {
    const email = `reserva-${Date.now()}@demo.test`;

    await page.goto('/finanzas-claras');
    const booking = page.locator('#reserva');
    // A day a few days ahead: well before the cancellation limit.
    const day = booking.locator('details').nth(4);
    await day.locator('summary').click();
    await day.getByRole('radio').first().check();
    await booking.getByLabel('Nombre completo').fill('Reserva de Prueba');
    await booking.getByLabel('Correo electrónico').fill(email);
    await booking.getByLabel(/Autorizo el tratamiento de mis datos/).check();
    await page.waitForTimeout(3500);
    await booking.getByRole('button', { name: 'Reservar mi diagnóstico' }).click();
    await expect(booking.getByRole('status')).toContainText('Listo');

    // The confirmation carries the link to manage the session.
    let link = '';
    await expect(async () => {
        const found = await (await request.get(`${MAILPIT}/api/v1/search?query=to:${encodeURIComponent(email)}`)).json();
        expect(found.messages.length).toBeGreaterThan(0);
        const message = await (await request.get(`${MAILPIT}/api/v1/message/${found.messages[0].ID}`)).json();
        link = /\/finanzas-claras\/reservar\/[\w-]+/.exec(message.Text)?.[0] ?? '';
        expect(link).not.toBe('');
    }).toPass({ timeout: 20_000 });

    await page.goto(link);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Tu sesión con Finanzas Claras');
    await page.getByLabel(/contarnos por qué/).fill('Me salió un viaje');
    await page.getByRole('button', { name: 'Cancelar mi sesión' }).click();
    await expect(page.getByRole('status')).toContainText('cancelada');

    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('asesor@pontiac.test');
    await page.getByLabel('Contraseña').fill(PASSWORD);
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await page.getByRole('link', { name: 'Agenda', exact: true }).click();
    await page.getByRole('tab', { name: 'Sesiones' }).click();
    await page.getByPlaceholder(/Buscar por nombre o correo/).fill(email);
    const row = page.getByRole('row').filter({ hasText: 'Reserva de Prueba' });
    await expect(row).toContainText('Me salió un viaje');
    await expect(row).toHaveAttribute('title', 'Cancelada');
});
