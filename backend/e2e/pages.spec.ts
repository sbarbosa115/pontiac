import { expect, test } from '@playwright/test';

// The demo consultant's published home page and its owner (`app:seed-demo`, README "Demo accounts").
const PASSWORD = process.env.E2E_PASSWORD ?? 'demo-password-123';

test('a visitor answers a page and the consultant finds them sorted in Prospectos', async ({ page }) => {
    const email = `visita-${Date.now()}@demo.test`;

    await page.goto('/finanzas-claras');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await page.getByLabel('Nombre completo').fill('Visita de Prueba');
    await page.getByLabel('Correo electrónico').fill(email);
    await page.getByLabel('¿Qué te preocupa más de tus finanzas?').selectOption('Mi pensión');
    await page.getByLabel(/Autorizo el tratamiento de mis datos/).check();
    // A form sent back instantly is a script's: a person takes a few seconds.
    await page.waitForTimeout(3500);
    await page.getByRole('button', { name: 'Quiero mi diagnóstico' }).last().click();
    await expect(page.getByRole('status')).toContainText('Gracias');

    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('asesor@pontiac.test');
    await page.getByLabel('Contraseña').fill(PASSWORD);
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await page.getByRole('link', { name: 'Prospectos', exact: true }).click();
    await page.getByPlaceholder('Buscar por nombre, correo o teléfono').fill(email);
    const row = page.getByRole('row').filter({ hasText: email });
    await expect(row).toContainText('Pensión');

    await row.getByRole('button', { name: 'Ver' }).click();
    await expect(page.getByText('Mi pensión')).toBeVisible();
});

test('the page editor previews a change before it is published', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('asesor@pontiac.test');
    await page.getByLabel('Contraseña').fill(PASSWORD);
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await page.getByRole('link', { name: 'Páginas', exact: true }).click();
    await page.getByRole('row').filter({ hasText: 'Plan de 2 sesiones' }).getByRole('button', { name: 'Editar' }).click();

    const heading = page.getByLabel(/^Título/).first();
    await heading.fill('Un título solo en el borrador');
    await expect(page.frameLocator('.editor-preview iframe').getByRole('heading', { level: 1 })).toHaveText('Un título solo en el borrador');
    await expect(page.getByRole('button', { name: 'Guardar borrador' })).toBeEnabled();
});
