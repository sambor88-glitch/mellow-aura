import { expect, test } from '@playwright/test';

// The full-screen menu on a phone (MA-203): „Menu” has to answer every tap.

test.beforeEach(({}, testInfo) => {
    test.skip(testInfo.project.name !== 'iphone', 'The menu button exists only below 920 px.');
});

test('the header stays under a finger and for a few pixels of drift', async ({ page }) => {
    await page.goto('/sklep', { waitUntil: 'networkidle' });
    const header = page.locator('[data-aura-header]');
    // The class itself, not the Tailwind variants like „[&.is-away]:opacity-0” that are always there.
    const away = /(^|\s)is-away(\s|$)/;
    const settle = () => page.waitForTimeout(400);

    // A tap moves the page a little; the pill used to leave from under the finger.
    await page.evaluate(() => scrollTo(0, 220));
    await settle();
    await page.evaluate(() => scrollBy(0, 40));
    await settle();
    await expect(header).not.toHaveClass(away);

    // While a finger rests on the pill, scrolling does not take it away.
    await header.dispatchEvent('touchstart');
    await page.evaluate(() => scrollBy(0, 600));
    await settle();
    await expect(header).not.toHaveClass(away);

    // Lifted, the next scroll down does.
    await page.evaluate(() => dispatchEvent(new Event('touchend')));
    await page.evaluate(() => scrollBy(0, 600));
    await expect(header).toHaveClass(away);
});

test('the menu opens and closes on a phone set to English', async ({ browser }, testInfo) => {
    // An English phone on a Polish page sees the language hint, which used to cover „Zamknij”.
    const context = await browser.newContext({ ...testInfo.project.use, locale: 'en-US' });
    const page = await context.newPage();
    await page.goto('/');

    const menu = page.locator('#menu');
    for (let i = 0; i < 3; i++) {
        await page.getByRole('button', { name: 'Menu' }).tap();
        await expect(menu).toBeVisible();
        await menu.getByRole('button', { name: 'Zamknij' }).tap();
        await expect(menu).toBeHidden();
    }

    await context.close();
});
