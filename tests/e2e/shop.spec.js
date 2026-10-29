import { expect, test } from '@playwright/test';

// The shop on a phone (MA-258): the first products on the first screen, the search and „Sortuj” in one row,
// the sorting options as links in a folded list, the „Zrobię dla Ciebie” shortcuts under the products.

// A Polish phone: without it the English suggestion bar would take the top of the screen.
test.use({ locale: 'pl-PL' });

const settle = (page) => page.waitForTimeout(700);

const watchErrors = (page) => {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));
    return errors;
};

const noSidewaysScroll = async (page) => {
    const { scrollWidth, innerWidth } = await page.evaluate(() => ({ scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth }));
    expect(scrollWidth, 'the shop scrolls sideways').toBeLessThanOrEqual(innerWidth);
};

test('a phone sees the first products on the first screen and sorts from one button', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'iphone', 'The folded sorting exists only on a phone.');
    const errors = watchErrors(page);

    await page.goto('/sklep', { waitUntil: 'networkidle' });
    await settle(page);

    const firstCard = page.locator('[data-shelf-card]').first();
    const box = await firstCard.boundingBox();
    const height = page.viewportSize().height;
    // At least the top of the photo shows without scrolling.
    expect(box.y, 'the first product starts below the first screen').toBeLessThan(height - 160);

    const search = page.getByRole('search');
    const sort = page.locator('details summary');
    await expect(sort).toBeVisible();
    await expect(sort).toContainText('Sortuj: Polecane');
    const searchBox = await search.boundingBox();
    const sortBox = await sort.boundingBox();
    expect(searchBox.height).toBeGreaterThanOrEqual(44);
    expect(sortBox.height).toBeGreaterThanOrEqual(44);
    expect(Math.abs(searchBox.y - sortBox.y), 'the search and the sorting share a row').toBeLessThan(4);
    await noSidewaysScroll(page);
    await page.screenshot({ path: testInfo.outputPath('shop-390.png') });

    await sort.click();
    const option = page.locator('details a', { hasText: 'Cena rosnąco' });
    await expect(option).toBeVisible();
    await noSidewaysScroll(page);
    await page.screenshot({ path: testInfo.outputPath('shop-sort-open-390.png') });

    await option.click();
    await page.waitForURL(/sort=price_asc/);
    await expect(page.locator('details summary')).toContainText('Sortuj: Cena rosnąco');

    const shortcuts = page.locator('#shop-shortcuts');
    await shortcuts.scrollIntoViewIfNeeded();
    await settle(page);
    const lastCard = await page.locator('[data-shelf-card]').last().boundingBox();
    expect((await shortcuts.boundingBox()).y, 'the shortcuts come after the products').toBeGreaterThan(lastCard.y);
    await noSidewaysScroll(page);
    await page.screenshot({ path: testInfo.outputPath('shop-shortcuts-390.png') });

    expect(errors).toEqual([]);
});

test('a computer keeps the sorting as a row', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The row exists only on a wide screen.');
    const errors = watchErrors(page);

    await page.goto('/sklep', { waitUntil: 'networkidle' });
    await settle(page);

    await expect(page.locator('details summary')).toBeHidden();
    await expect(page.locator('main a[aria-current="true"]', { hasText: 'Polecane' }).last()).toBeVisible();
    await noSidewaysScroll(page);
    await page.screenshot({ path: testInfo.outputPath('shop-1280.png') });
    await page.locator('#shop-shortcuts').scrollIntoViewIfNeeded();
    await settle(page);
    await page.screenshot({ path: testInfo.outputPath('shop-shortcuts-1280.png') });

    expect(errors).toEqual([]);
});
