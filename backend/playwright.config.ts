// End-to-end tests: a few critical flows driven in a real browser against the running dev stack.
// Run them in their own container (Playwright's image has the browsers):
//     docker compose --profile e2e run --rm e2e
// They expect the demo data (`app:seed-demo`, README "Local demo accounts").
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: 'e2e',
    outputDir: 'e2e/.results',
    // The flows share one database: run them one at a time.
    workers: 1,
    fullyParallel: false,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8080',
        locale: 'es-CO',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
