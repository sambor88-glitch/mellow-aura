import { expect, test } from '@playwright/test';

// One card in the shop and in the gift finder, „Sklep” in the breadcrumbs and the missing page with the menu (MA-259).

/** Fails the test on a JavaScript error or console.error. */
function watchErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
    page.on('console', (message) => message.type() === 'error' && !message.text().includes('404') && errors.push(`console.error: ${message.text()}`));

    return errors;
}

async function expectNoSidewaysScroll(page) {
    const { scrollWidth, innerWidth } = await page.evaluate(() => ({ scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth }));
    expect(scrollWidth, `${page.url()} scrolls sideways`).toBeLessThanOrEqual(innerWidth);
}

for (const path of ['/sklep', '/prezenty']) {
    test(`a single size goes to the cart from the card on ${path} without leaving the page`, async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto(path, { waitUntil: 'networkidle' });
        await expectNoSidewaysScroll(page);

        const form = page.locator('[data-shelf-card] form').first();
        await form.scrollIntoViewIfNeeded();
        await page.screenshot({ path: testInfo.outputPath(`cards${path.replace('/', '-')}.png`) });
        await form.getByRole('button').click();

        await expect(page.locator('[x-text="$store.cart.count"]').first()).toHaveText('1');
        expect(new URL(page.url()).pathname).toBe(path);
        expect(errors).toEqual([]);
    });
}

test('the shop breadcrumbs say „Sklep”', async ({ page }) => {
    await page.goto('/sklep');
    await expect(page.getByRole('navigation', { name: 'Okruszki' })).toHaveText(/Strona główna\s*\/\s*Sklep/);
});

test('the missing page keeps the menu, the cart and the footer', async ({ page }, testInfo) => {
    const errors = watchErrors(page);
    const response = await page.goto('/nie-ma-takiej-strony', { waitUntil: 'networkidle' });

    expect(response.status()).toBe(404);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Tej strony tu nie ma');
    await expect(page.locator('[data-aura-header]')).toBeVisible();
    await expect(page.locator('footer')).toBeAttached();
    await expect(page.getByRole('link', { name: 'Przejdź do sklepu' })).toHaveAttribute('href', /\/sklep$/);
    await expectNoSidewaysScroll(page);
    await page.screenshot({ path: testInfo.outputPath('404.png'), fullPage: true });
    expect(errors).toEqual([]);
});
