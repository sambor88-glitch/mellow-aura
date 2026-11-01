import { defineConfig, devices } from '@playwright/test';

// The panel audit on a phone (MA-335). The server runs on its own (.github/workflows/panel-audit.yml), so the
// browser tests can fill the shop first and the audit walks the panel on the same database:
//   npx playwright test -c playwright.audit.config.js --project=flows   # orders, bookings, cards, ideas
//   npx playwright test -c playwright.audit.config.js --project=audit   # every panel screen at 390 and 360 px
const port = process.env.E2E_PORT ?? '8123';

export default defineConfig({
    workers: 1,
    fullyParallel: false,
    retries: 0,
    timeout: 120_000,
    reporter: 'list',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        navigationTimeout: 20_000,
        actionTimeout: 15_000,
    },
    projects: [
        { name: 'flows', testDir: './tests/e2e', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } } },
        { name: 'audit', testDir: './tests/audit', use: { ...devices['Desktop Chrome'] } },
    ],
});
