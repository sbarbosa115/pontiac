import { expect, test } from '@playwright/test';

// The demo super admin (`app:seed-demo`, README "Demo accounts").
const PASSWORD = process.env.E2E_PASSWORD ?? 'demo-password-123';

test('a super admin creates a consultant, who is invited and listed', async ({ page }) => {
    const stamp = Date.now();
    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('admin@pontiac.test');
    await page.getByLabel('Contraseña').fill(PASSWORD);
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await expect(page).toHaveURL(/\/plataforma$/);

    await page.getByRole('link', { name: 'Asesores', exact: true }).click();
    await page.getByRole('button', { name: 'Nuevo asesor' }).first().click();
    const dialog = page.getByRole('dialog', { name: 'Nuevo asesor' });
    await dialog.getByLabel('Nombre de la práctica').fill(`Prueba ${stamp}`);
    // The address follows the name.
    await expect(dialog.getByLabel(/^Dirección/)).toHaveValue(`prueba-${stamp}`);
    await dialog.getByLabel('Nombre del asesor').fill('Ana Prueba');
    await dialog.getByLabel(/^Correo del asesor/).fill(`ana-${stamp}@pontiac.test`);
    await dialog.getByRole('button', { name: 'Crear e invitar' }).click();

    await expect(page.getByText(`Creamos a Prueba ${stamp} y enviamos la invitación a ana-${stamp}@pontiac.test.`)).toBeVisible();
    const row = page.getByRole('row').filter({ hasText: `/prueba-${stamp}` });
    await expect(row.getByRole('button', { name: 'Reenviar invitación' })).toBeVisible();

    // Its page opens on Datos, and Límites y funciones shows what the defaults gave it.
    await row.getByRole('button', { name: 'Ver' }).click();
    await expect(page.getByRole('heading', { level: 1, name: `Prueba ${stamp}` })).toBeVisible();
    await page.getByRole('tab', { name: 'Límites y funciones' }).click();
    await expect(page.getByLabel('Portal de clientes')).toBeChecked();
});
