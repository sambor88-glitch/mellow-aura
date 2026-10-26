import { defineConfig, devices } from '@playwright/test';

// Browser test of the whole purchase: shop → checkout → panel. How to run it: CLAUDE.md, „Komendy”.
const port = process.env.E2E_PORT ?? '8123';

export default defineConfig({
    testDir: './tests/e2e',
    // One shop, one database: the two screen sizes buy one after another.
    workers: 1,
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    timeout: 120_000,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        navigationTimeout: 20_000,
        actionTimeout: 15_000,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } } },
        { name: 'iphone', use: { ...devices['iPhone 13'], browserName: 'chromium' } },
    ],
    webServer: {
        command: 'bash tests/e2e/serve.sh',
        url: `http://127.0.0.1:${port}/up`,
        timeout: 180_000,
        reuseExistingServer: false,
        stdout: 'pipe',
        env: { E2E_PORT: port },
    },
});
