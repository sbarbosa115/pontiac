import { expect, test } from '@playwright/test';

// The demo consultant's home page feeds the "Diagnóstico gratuito" flow, whose "Sesión agendada" stage sends an email
// (`app:seed-demo`, README "Demo accounts").
const PASSWORD = process.env.E2E_PASSWORD ?? 'demo-password-123';
const MAILPIT = process.env.MAILPIT_URL ?? 'http://localhost:8025';

test('a visitor enters the flow from the page, the consultant moves them on the board, and they stop the emails', async ({ page, request }) => {
    const stamp = Date.now();
    const name = `Flujo ${stamp}`;
    const email = `flujo-${stamp}@demo.test`;

    await page.goto('/finanzas-claras');
    const form = page.locator('#formulario');
    await form.getByLabel('Nombre completo').fill(name);
    await form.getByLabel('Correo electrónico').fill(email);
    await form.getByLabel('¿Qué te preocupa más de tus finanzas?').selectOption('Mis deudas');
    await form.getByLabel(/Autorizo el tratamiento de mis datos/).check();
    // A form sent back instantly is a script's: a person takes a few seconds.
    await page.waitForTimeout(3500);
    await form.getByRole('button', { name: 'Quiero mi diagnóstico' }).click();
    await expect(form.getByRole('status')).toContainText('Gracias');

    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('asesor@pontiac.test');
    await page.getByLabel('Contraseña').fill(PASSWORD);
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await page.getByRole('link', { name: 'Prospectos', exact: true }).click();
    await page.getByRole('tab', { name: 'Tablero' }).click();

    // In the start stage; moved by hand to the stage that greets them.
    await expect(page.getByRole('listitem', { name: 'Nuevo' })).toContainText(name);
    await page.getByLabel(`Mover a ${name} a otra etapa`).selectOption({ label: 'Sesión agendada' });
    await expect(page.getByRole('listitem', { name: 'Sesión agendada' })).toContainText(name);

    // The stage's email, with the link to stop them.
    let link = '';
    await expect(async () => {
        const found = await (await request.get(`${MAILPIT}/api/v1/search?query=to:${encodeURIComponent(email)}`)).json();
        const stage = found.messages.find((m: { Subject: string }) => m.Subject.startsWith('Nos vemos pronto'));
        expect(stage).toBeDefined();
        const message = await (await request.get(`${MAILPIT}/api/v1/message/${stage.ID}`)).json();
        link = /\/finanzas-claras\/correos\/baja\/[\w-]+\/[0-9a-f]+/.exec(message.Text)?.[0] ?? '';
        expect(link).not.toBe('');
    }).toPass({ timeout: 20_000 });

    // Their history keeps the move, by whom.
    await page.locator('article').filter({ hasText: name }).getByRole('button', { name: 'Ver' }).click();
    await page.getByRole('tab', { name: 'Historial' }).click();
    await expect(page.getByRole('row').filter({ hasText: '«Nuevo» → «Sesión agendada»' })).toContainText('Por Andrés Asesor');

    await page.goto(link);
    await page.getByRole('button', { name: 'No quiero recibir más correos' }).click();
    await expect(page.getByRole('status')).toContainText('no te enviaremos más correos de seguimiento');
});
