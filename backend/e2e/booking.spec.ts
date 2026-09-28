import { expect, test } from '@playwright/test';

// The demo consultant's home page books its free diagnostic (`app:seed-demo`, README "Demo accounts").
const PASSWORD = process.env.E2E_PASSWORD ?? 'demo-password-123';
const MAILPIT = process.env.MAILPIT_URL ?? 'http://localhost:8025';

test('a visitor books a free session, cancels it from the emailed link, and the consultant sees it', async ({ page, request }) => {
    const email = `reserva-${Date.now()}@demo.test`;

    await page.goto('/finanzas-claras');
    const booking = page.locator('#reserva');
    // A day a few days ahead, well before the cancellation limit: in the next month when this one is ending.
    const days = booking.locator('.cal-month:visible .cal-day');
    if ((await days.count()) < 5) {
        await booking.locator('.cal-month:visible label[title="Mes siguiente"]').click();
        await days.nth(2).click();
    } else {
        await days.nth(4).click();
    }
    await booking.locator('.cal-times:visible').getByRole('radio').first().check();
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
        // The home page also feeds a flow, whose stage email arrives beside the confirmation.
        for (const { ID } of found.messages) {
            const message = await (await request.get(`${MAILPIT}/api/v1/message/${ID}`)).json();
            link ||= /\/finanzas-claras\/reservar\/[\w-]+/.exec(message.Text)?.[0] ?? '';
        }
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
    // By this run's email: the name is the same on every run.
    const row = page.getByRole('row').filter({ hasText: email });
    await expect(row).toContainText('Me salió un viaje');
    await expect(row).toHaveAttribute('title', 'Cancelada');
});
