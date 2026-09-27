import { createHash } from 'node:crypto';
import { expect, test } from '@playwright/test';

// The demo consultant sells Plan A on "plan-2-sesiones", with Wompi test keys that are not real (app:seed-demo). The
// checkout at Wompi is never opened: the test reads our redirect to it, then plays Wompi's part with an event signed with
// the demo events secret, as Wompi would sign it.
const PASSWORD = process.env.E2E_PASSWORD ?? 'demo-password-123';
const EVENTS_SECRET = 'test_events_pontiacdemo';

test('a visitor pays a plan through Wompi and the consultant finds the payment', async ({ page, request }) => {
    const email = `pago-${Date.now()}@demo.test`;
    // Our answer to the form is a redirect to Wompi: read where it goes instead of following it (a redirect's
    // target is not routed by Playwright).
    let checkout = '';
    await page.route('**/finanzas-claras/plan-2-sesiones/pagar', async (route) => {
        const response = await route.fetch({ maxRedirects: 0 });
        checkout = response.headers()['location'] ?? '';
        await route.fulfill({ status: 200, contentType: 'text/html', body: '<h1>Wompi</h1>' });
    });

    await page.goto('/finanzas-claras/plan-2-sesiones');
    const section = page.locator('#precios');
    await expect(section.locator('.price-card-price')).toContainText('250.000');
    await section.getByLabel('Nombre completo').fill('Pago de Prueba');
    await section.getByLabel('Correo electrónico').fill(email);
    await section.getByLabel(/Autorizo el tratamiento de mis datos/).check();
    await page.waitForTimeout(3500);
    await section.getByRole('button', { name: 'Pagar con Wompi' }).click();
    await expect.poll(() => checkout).toContain('checkout.wompi.co/p/');

    const query = new URL(checkout).searchParams;
    const reference = query.get('reference') ?? '';
    expect(query.get('amount-in-cents')).toBe('25000000');
    expect(query.get('customer-data:email')).toBe(email);

    // Wompi's event, to the URL the consultant pastes in Wompi's dashboard.
    const login = await (await request.post('/api/login', { data: { email: 'asesor@pontiac.test', password: PASSWORD } })).json();
    const settings = await (await request.get('/api/admin/wompi', { headers: { Authorization: `Bearer ${login.token}` } })).json();
    const transaction = { id: `e2e-${Date.now()}`, amount_in_cents: 25000000, reference, currency: 'COP', payment_method_type: 'NEQUI', status: 'APPROVED' };
    const timestamp = Math.floor(Date.now() / 1000);
    const checksum = createHash('sha256').update(`${transaction.id}${transaction.status}${transaction.amount_in_cents}${timestamp}${EVENTS_SECRET}`).digest('hex');
    const event = await request.post(new URL(settings.eventsUrl).pathname, {
        data: { event: 'transaction.updated', data: { transaction }, environment: 'test', signature: { properties: ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'], checksum }, timestamp },
    });
    expect(await event.json()).toEqual({ status: 'applied' });

    await page.goto(`/finanzas-claras/pago/${reference}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('¡Pago aprobado!');

    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('asesor@pontiac.test');
    await page.getByLabel('Contraseña').fill(PASSWORD);
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await page.getByRole('link', { name: 'Pagos', exact: true }).click();
    await page.getByPlaceholder(/Buscar por persona/).fill(email);
    const row = page.getByRole('row').filter({ hasText: reference });
    await expect(row).toContainText('Nequi');
    await expect(row).toHaveAttribute('title', 'Aprobado');
});
