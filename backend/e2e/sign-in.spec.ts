import { expect, test } from '@playwright/test';

// The demo logins (`app:seed-demo`, README "Demo accounts"): every one has the same password.
const PASSWORD = process.env.E2E_PASSWORD ?? 'demo-password-123';

test('a consultant signs in, opens their team and the invitation modal', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('asesor@pontiac.test');
    await page.getByLabel('Contraseña').fill(PASSWORD);
    await page.getByRole('button', { name: 'Ingresar' }).click();

    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole('heading', { level: 1, name: /Hola, Andrés Asesor/ })).toBeVisible();

    await page.getByRole('link', { name: 'Ajustes' }).click();
    await expect(page.getByRole('cell', { name: 'Sofía Asistente' })).toBeVisible();

    // A modal renders (a missing import would only show up here), and a wrong email is refused under its field.
    await page.getByRole('button', { name: 'Invitar asistente' }).first().click();
    const dialog = page.getByRole('dialog', { name: 'Invitar asistente' });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Correo').fill('no-es-un-correo');
    await dialog.getByRole('button', { name: 'Enviar invitación' }).click();
    await expect(dialog.getByText('Este valor no es una dirección de email válida.')).toBeVisible();
});

test('a client signs in at their consultant\'s portal, and only there', async ({ page }) => {
    await page.goto('/finanzas-claras/portal');
    await expect(page).toHaveURL(/\/finanzas-claras\/portal\/ingresar$/);

    await page.getByLabel('Correo electrónico').fill('cliente@pontiac.test');
    await page.getByLabel('Contraseña').fill(PASSWORD);
    await page.getByRole('button', { name: 'Ingresar' }).click();

    await expect(page).toHaveURL(/\/finanzas-claras\/portal$/);
    await expect(page.getByRole('heading', { level: 1, name: /Hola, Carlos Cliente/ })).toBeVisible();

    await page.goto('/admin');
    await expect(page).toHaveURL(/\/finanzas-claras\/portal$/);
});
