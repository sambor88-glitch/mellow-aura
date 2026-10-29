import { expect, test } from '@playwright/test';

// The price and „Do koszyka” pinned to the bottom of a phone (MA-255): out of the way while the sizes or the form
// are on the screen, back while the description below is read.

test.beforeEach(({}, testInfo) => {
    test.skip(testInfo.project.name !== 'iphone', 'The bar exists only below 872 px.');
});

const settle = (page) => page.waitForTimeout(700);

test('the bar on a product page never covers the sizes', async ({ page }, testInfo) => {
    await page.goto('/produkt/wazony', { waitUntil: 'networkidle' });
    const bar = page.locator('[x-data="buyBar"]');
    const sizes = page.locator('[data-buy-zone]').nth(1);

    await sizes.scrollIntoViewIfNeeded();
    await settle(page);
    await expect(sizes).toBeInViewport();
    await expect(bar).toBeHidden();
    await page.screenshot({ path: testInfo.outputPath('product-sizes-390.png') });

    // Below the form, among the shipping and care notes, the bar is back.
    await page.evaluate(() => scrollTo(0, document.querySelector('#add-to-cart').getBoundingClientRect().bottom + scrollY + 40));
    await settle(page);
    await expect(page.locator('#add-to-cart')).not.toBeInViewport();
    await expect(bar).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('product-description-390.png') });
});

test('the bar on the mug configurator stays away from the whole form', async ({ page }, testInfo) => {
    await page.goto('/kubek-z-napisem', { waitUntil: 'networkidle' });
    const bar = page.locator('[x-data^="buyBar"]');

    await page.locator('#mug-form input[name="size"]').first().locator('..').scrollIntoViewIfNeeded();
    await settle(page);
    await expect(bar).toBeHidden();
    await page.screenshot({ path: testInfo.outputPath('mug-sizes-390.png') });

    await page.evaluate(() => scrollTo(0, document.querySelector('#mug-form').getBoundingClientRect().bottom + scrollY + 40));
    await settle(page);
    await expect(bar).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('mug-below-form-390.png') });
});
