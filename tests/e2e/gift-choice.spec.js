import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

// „Podaruj wybór” (MA-235): the buyer switches the mug configurator to the card, pays for the size and the delivery,
// and the person it is for types the code, writes her text, picks the glaze and the parcel locker.

const logFile = new URL('../../storage/logs/laravel.log', import.meta.url);

/** Fails the test on a JavaScript error, console.error or a server error on any page. */
function watchForErrors(page) {
    const errors = [];

    page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(`console.error: ${message.text()} (${message.location().url})`);
        }
    });
    page.on('response', (response) => {
        if (response.status() >= 500) {
            errors.push(`HTTP ${response.status()}: ${response.request().method()} ${response.url()}`);
        }
    });

    return errors;
}

async function expectNoSideScroll(page) {
    const { scrollWidth, innerWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        innerWidth: window.innerWidth,
    }));

    expect(scrollWidth, `${page.url()} scrolls sideways`).toBeLessThanOrEqual(innerWidth);
}

/** The card's code from the mail the log mailer wrote to the buyer: its subject is „Karta wyboru MA-XXXX-XXXX”. */
function cardCodeFor(email) {
    const entries = readFileSync(logFile, 'utf8').split(/^\[\d{4}-\d{2}-\d{2} [^\]]+\] /m);
    const mail = entries.find((entry) => entry.includes(email) && /Karta wyboru|Karta_wyboru/.test(entry));

    return mail?.match(/MA-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}/)?.[0];
}

/** Signed in, the forms count against the account's limit, not the one address all the tests share. */
async function signIn(page, testInfo) {
    await page.goto('/panel/logowanie');
    await page.locator('[name="email"]').fill(`e2e-${testInfo.project.name}@mellow-aura.test`);
    await page.locator('[name="password"]').fill(process.env.E2E_ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Wejdź do panelu' }).click();
    await expect(page.getByRole('button', { name: 'Wejdź do panelu' })).toHaveCount(0);
}

test('a card bought in the configurator is chosen on by the person it is for', async ({ page }, testInfo) => {
    const errors = watchForErrors(page);
    const email = `e2e-gift-${testInfo.project.name}-${Date.now()}@mellow-aura.test`;

    await signIn(page, testInfo);

    await test.step('the configurator switches to the card', async () => {
        await page.goto('/kubek-z-napisem', { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: 'Niech wybierze obdarowana' }).click();
        await expect(page.locator('#gift-form')).toBeVisible();
        await expect(page.locator('#mug-form')).toBeHidden();
        await expect(page.getByRole('button', { name: 'Niech wybierze obdarowana' })).toHaveAttribute('aria-pressed', 'true');

        await page.locator('#gift-for').fill('Ola');
        await page.locator('#gift-from').fill('Anna');
        await page.locator('#gift-note').fill('Napisz, co chcesz');
        await expectNoSideScroll(page);
        await page.screenshot({ path: testInfo.outputPath('configurator-card.png'), fullPage: true });

        await page.locator('#gift-form button[type="submit"]').click();
        await expect(page.getByText('Karta wyboru w koszyku').first()).toBeVisible();
    });

    await test.step('the buyer pays without a delivery of her own', async () => {
        await page.goto('/zamowienie');
        await expect(page.locator('legend:has-text("Sposób dostawy")')).toHaveCount(0);
        await page.locator('[name="email"]').fill(email);
        await page.locator('[name="phone"]').fill('600100200');
        await page.locator('[name="name"]').fill('Anna Testowa');
        await page.locator('input[name="payment_method"][value="blik"]').check({ force: true });
        await page.locator('[name="accept_terms"]').check();
        await page.locator('#blik_code').fill('123456');
        await page.locator('form[action$="/zamowienie"] button[type="submit"]').click();
        await page.waitForURL('**/zamowienie/potwierdzenie');
    });

    const code = cardCodeFor(email);
    expect(code, 'the buyer got the card with its code').toBeTruthy();

    await test.step('she types the code, writes her text and picks the locker', async () => {
        await page.goto('/wybierz-kubek');
        await expectNoSideScroll(page);
        await page.locator('[name="code"]').fill(code.toLowerCase());
        await page.getByRole('button', { name: 'Przejdź do wyboru napisu' }).click();

        await expect(page.getByRole('heading', { name: 'Ola, wymyśl swój napis' })).toBeVisible();
        await page.locator('#mug-text').fill('kawa najpierw');
        await page.locator('label[title="Kobalt"]').click();
        await expect(page.locator('input[name="glaze"][value="cobalt"]')).toBeChecked();
        await page.locator('[name="name"]').fill('Ola Testowa');
        await page.locator('[name="email"]').fill(`ola-${email}`);
        await page.locator('[name="phone"]').fill('600200300');
        await page.locator('input[name="shipping_method"][value="parcel_locker"]').check({ force: true });
        await page.locator('[name="locker_code"]').fill('KRA01M');
        await expectNoSideScroll(page);
        await page.screenshot({ path: testInfo.outputPath('choice-page.png'), fullPage: true });

        await page.getByRole('button', { name: 'Zamawiam ten kubek' }).click();
        await expect(page.getByRole('heading', { name: 'Kubek wybrany' })).toBeVisible();
        await expect(page.getByText('KAWA NAJPIERW')).toBeVisible();
        await expectNoSideScroll(page);
    });

    expect(errors).toEqual([]);
});
