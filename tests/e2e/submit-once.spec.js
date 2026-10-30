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

    const form = page.locator('form[data-submit-once]');
    await expect(form.getByRole('button', { name: 'Wyślij wiadomość' })).toBeVisible();
    // A click through the page itself: Playwright's click would wait for the navigation held back above.
    await form.evaluate((element) => element.querySelector('button:not([type="button"])').click());
    await expect(form).toHaveAttribute('aria-busy', 'true');

    // The button no longer takes taps, so the second one goes to the form itself, as a script or Enter would.
    await form.evaluate((element) => element.requestSubmit());
    await page.waitForTimeout(300);
    expect(posts).toHaveLength(1);

    const loaded = page.waitForEvent('load');
    release();
    await loaded;
    expect(posts).toHaveLength(1);
    expect(errors).toEqual([]);
});
