import { expect, test } from '@playwright/test';

// A form marked data-submit-once goes to the server once (MA-282): a second tap while the next page loads
// sends nothing, and the button looks pressed until then.

test('a second tap on „Wyślij wiadomość” sends no second message', async ({ page }) => {
    const errors = [];
    const posts = [];
    page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
    page.on('console', (message) => message.type() === 'error' && errors.push(`console.error: ${message.text()}`));
    page.on('request', (request) => request.method() === 'POST' && new URL(request.url()).pathname === '/kontakt' && posts.push(request));

    // A slow answer keeps the page waiting, like a phone on a poor connection.
    let release;
    const answered = new Promise((resolve) => (release = resolve));
    await page.route('**/kontakt', async (route) => {
        if (route.request().method() === 'POST') {
            await answered;
        }
        await route.continue();
    });

    await page.goto('/kontakt', { waitUntil: 'networkidle' });
    await page.locator('#contact-name').fill('Ola');
    await page.locator('#contact-email').fill('ola@example.test');
    await page.locator('#contact-message').fill('Czy kubek mieści 400 ml?');

    await expect(page.locator('form[data-submit-once]').getByRole('button', { name: 'Wyślij wiadomość' })).toBeVisible();

    // Both taps inside the page: while the navigation is held back, Playwright's click and locators wait for it.
    // The second one goes to the form itself, as a script or Enter would, because the button no longer takes taps.
    const busy = await page.evaluate(async () => {
        const form = document.querySelector('form[data-submit-once]');
        form.querySelector('button:not([type="button"])').click();
        const busyAfterFirst = form.getAttribute('aria-busy');
        await new Promise((resolve) => setTimeout(resolve, 150));
        form.requestSubmit();
        await new Promise((resolve) => setTimeout(resolve, 300));

        return busyAfterFirst;
    });
    expect(busy).toBe('true');
    expect(posts).toHaveLength(1);

    const loaded = page.waitForEvent('load');
    release();
    await loaded;
    expect(posts).toHaveLength(1);
    expect(errors).toEqual([]);
});
