import { expect, test } from '@playwright/test';

// viewport-fit=cover (MA-302): the page reaches under the notch and the gesture bar of an iPhone, so whatever
// is pinned to an edge keeps clear of them. Chromium stands in for Safari with the iPhone 13's insets.

test.beforeEach(({}, testInfo) => {
    test.skip(testInfo.project.name !== 'iphone', 'Insets exist only on a phone.');
});

const PORTRAIT = { viewport: { width: 390, height: 844 }, insets: { top: 47, bottom: 34, left: 0, right: 0 } };
const LANDSCAPE = { viewport: { width: 844, height: 390 }, insets: { top: 0, bottom: 21, left: 47, right: 47 } };

const turn = async (page, { viewport, insets }) => {
    await page.setViewportSize(viewport);
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('Emulation.setSafeAreaInsetsOverride', { insets });
};

const box = (locator) => locator.evaluate((element) => element.getBoundingClientRect().toJSON());

test('upright, the header, the buy bar and the cart stay off the notch and the gesture bar', async ({ page }, testInfo) => {
    await turn(page, PORTRAIT);
    await page.goto('/produkt/wazony', { waitUntil: 'networkidle' });

    expect((await box(page.locator('[data-aura-header]'))).top).toBeGreaterThanOrEqual(47);

    // Below the form the pinned bar comes back, above the gesture bar.
    await page.evaluate(() => scrollTo(0, document.querySelector('#add-to-cart').getBoundingClientRect().bottom + scrollY + 40));
    await page.waitForTimeout(700);
    const bar = page.locator('[x-data="buyBar"]');
    await expect(bar).toBeVisible();
    expect((await box(bar)).bottom).toBeLessThanOrEqual(844 - 34);

    await page.locator('button[aria-controls="cart"]').click();
    const panel = page.locator('.aura-drawer-panel');
    await expect(panel).toBeVisible();
    const drawer = await box(panel);
    expect(drawer.top).toBeGreaterThanOrEqual(47);
    expect(drawer.bottom).toBeLessThanOrEqual(844 - 34);
    await page.screenshot({ path: testInfo.outputPath('safe-area-portrait-cart.png') });
});

test('on its side, the page and the menu stay clear of the notch', async ({ page }, testInfo) => {
    await turn(page, LANDSCAPE);
    await page.goto('/sklep', { waitUntil: 'networkidle' });

    const main = await box(page.locator('#tresc'));
    expect(main.left).toBeGreaterThanOrEqual(47);
    expect(main.right).toBeLessThanOrEqual(844 - 47);
    await page.screenshot({ path: testInfo.outputPath('safe-area-landscape-shop.png') });

    await page.locator('button[aria-controls="menu"]').click();
    const close = page.locator('#menu > button').first();
    await expect(close).toBeVisible();
    expect((await box(close)).right).toBeLessThanOrEqual(844 - 47);
    expect((await box(page.locator('#menu a').first())).left).toBeGreaterThanOrEqual(47);
    await page.screenshot({ path: testInfo.outputPath('safe-area-landscape-menu.png') });
});
