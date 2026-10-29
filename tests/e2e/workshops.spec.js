import { expect, test } from '@playwright/test';

// The workshops page (MA-256): „Terminy (n)” on a card shows that workshop's dates, the pills above the list
// switch between them, „Wybieram” stands in one column and nothing scrolls sideways.

async function signIn(page) {
    await page.goto('/panel/logowanie');
    await page.locator('[name="email"]').fill(process.env.E2E_ADMIN_EMAIL ?? 'e2e@mellow-aura.test');
    await page.locator('[name="password"]').fill(process.env.E2E_ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Wejdź do panelu' }).click();
    await expect(page.getByRole('button', { name: 'Wejdź do panelu' })).toHaveCount(0);
}

/** Two dates of „Lepienie kubków” and one of „Szkliwienie i malowanie”, added in the panel once for both screen sizes. */
async function addDates(page) {
    await page.goto('/warsztaty-ceramiczne-krakow');
    if (await page.locator('#workshop-dates').count()) {
        return;
    }

    await signIn(page);
    const day = (offset) => new Date(Date.now() + offset * 86_400_000).toISOString().slice(0, 10);
    for (const [workshop, offset, seats] of [['Lepienie kubków', 5, 6], ['Szkliwienie i malowanie', 6, 1], ['Lepienie kubków', 12, 4]]) {
        await page.goto('/panel/warsztaty');
        await page.locator('#termin-workshop').selectOption({ label: workshop });
        await page.locator('#termin-date').fill(day(offset));
        await page.locator('#termin-start').fill('11:00');
        await page.locator('#termin-end').fill('13:00');
        await page.locator('#termin-seats').fill(String(seats));
        await page.getByRole('button', { name: 'Dodaj do terminarza' }).click();
        await page.waitForLoadState('networkidle');
    }
}

test('a card leads to its own dates and the pills switch between workshops', async ({ page }) => {
    const errors = [];
    await addDates(page);
    page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
    page.on('console', (message) => message.type() === 'error' && errors.push(`console.error: ${message.text()}`));

    await page.goto('/warsztaty-ceramiczne-krakow', { waitUntil: 'networkidle' });
    const rows = page.locator('section[aria-labelledby="workshop-dates"] li');
    const pills = page.getByRole('group', { name: 'Pokaż terminy warsztatu' });
    await expect(rows.filter({ visible: true })).toHaveCount(3);

    // A workshop without dates asks about one instead.
    await expect(page.getByRole('link', { name: 'Zapytaj o termin' }).first()).toBeVisible();

    await page.getByRole('link', { name: 'Terminy (1)' }).click();
    await expect(rows.filter({ visible: true })).toHaveCount(1);
    await expect(rows.filter({ visible: true })).toContainText('Szkliwienie i malowanie');
    await expect(pills.getByRole('button', { name: 'Szkliwienie i malowanie' })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('#workshop-dates')).toBeInViewport();

    await pills.getByRole('button', { name: 'Lepienie kubków' }).click();
    await expect(rows.filter({ visible: true })).toHaveCount(2);

    await pills.getByRole('button', { name: 'Wszystkie' }).click();
    await expect(rows.filter({ visible: true })).toHaveCount(3);

    // Every „Wybieram” in one column, of one width.
    const buttons = await rows.getByRole('link', { name: /^Wybieram/ }).evaluateAll((links) => links.map((link) => link.getBoundingClientRect()).map(({ left, width }) => [Math.round(left), Math.round(width)]));
    expect(new Set(buttons.map(String)).size, JSON.stringify(buttons)).toBe(1);

    const { scrollWidth, innerWidth } = await page.evaluate(() => ({ scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth }));
    expect(scrollWidth, 'the page scrolls sideways').toBeLessThanOrEqual(innerWidth);
    expect(errors).toEqual([]);
});
