import { expect, test } from '@playwright/test';

// The panel on a computer and on a phone: the menu in groups under „Menu” (MA-249) and the warning before
// a change in one form is lost by saving another or by leaving the page (MA-251).

async function signIn(page) {
    await page.goto('/panel/logowanie');
    await page.locator('[name="email"]').fill(process.env.E2E_ADMIN_EMAIL ?? 'e2e@mellow-aura.test');
    await page.locator('[name="password"]').fill(process.env.E2E_ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Wejdź do panelu' }).click();
    await expect(page.getByRole('button', { name: 'Wejdź do panelu' })).toHaveCount(0);
}

test('the menu hides under a button on a phone and opens in groups', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'iphone', 'The menu button exists only below 768 px.');
    await signIn(page);
    await page.goto('/panel/zamowienia', { waitUntil: 'networkidle' });

    const button = page.locator('[aria-controls="panel-menu"]');
    const menu = page.locator('#panel-menu');

    // The orders come first: the menu waits under its button.
    await expect(menu).toBeHidden();
    await expect(button).toHaveAttribute('aria-expanded', 'false');

    await button.click();
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    await expect(menu.getByRole('heading', { name: 'Sprzedaż' })).toBeVisible();
    await expect(menu.getByRole('heading', { name: 'Ustawienia' })).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
    await expect(button).toBeFocused();
});

test('a change in one form is never lost silently', async ({ page }) => {
    const dialogs = [];
    page.on('dialog', (dialog) => {
        dialogs.push(dialog.type());
        dialog.dismiss();
    });
    await signIn(page);
    await page.goto('/panel/ustawienia', { waitUntil: 'networkidle' });

    const forms = page.locator('main form[method="post"]');
    const delivery = forms.first();
    const studio = forms.nth(1);
    const threshold = delivery.locator('[name="free_shipping_threshold"]');
    const question = page.locator('main [role="alert"]').filter({ hasText: 'Masz niezapisane zmiany' });

    await threshold.fill('401');
    await expect(delivery).toHaveAttribute('data-dirty', '');

    // Saving another card, in another tab (MA-321), asks in the page and stays here.
    await page.getByRole('tab', { name: 'Pracownia' }).click();
    await studio.locator('button:not([type="button"])').last().click();
    await expect(question).toContainText('Masz niezapisane zmiany w „Dostawa i opłaty”. Zapisz je najpierw albo porzuć.');
    expect(new URL(page.url()).pathname).toBe('/panel/ustawienia');

    // „Wróć do…” leads back to the changed card and opens its tab (MA-315).
    await question.getByRole('button', { name: 'Wróć do „Dostawa i opłaty”' }).click();
    await expect(question).toHaveCount(0);
    await expect(threshold).toBeVisible();
    await expect(threshold).toHaveValue('401');

    // A link in the menu asks too, and dropping the change follows it.
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.locator('#panel-menu').getByRole('link', { name: 'Produkty' }).click();
    await expect(question).toBeVisible();
    expect(new URL(page.url()).pathname).toBe('/panel/ustawienia');
    await question.getByRole('button', { name: 'Porzuć zmiany w „Dostawa i opłaty”' }).click();
    await page.waitForURL('**/panel/produkty');

    // Saving the changed card itself goes through without a word.
    await page.goto('/panel/ustawienia', { waitUntil: 'networkidle' });
    await threshold.fill('400');
    await delivery.locator('button:not([type="button"])').last().click();
    await page.waitForURL('**/panel/ustawienia**');
    await expect(question).toHaveCount(0);

    expect(dialogs, 'no browser dialog on the way').toEqual([]);
});
