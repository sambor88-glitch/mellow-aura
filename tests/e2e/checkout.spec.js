import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

// The whole purchase in a real browser, as a customer and then as Kasia in the panel:
// shop → product → checkout with BLIK and a gift message (TestGateway pays at once) → confirmation → empty cart
// → panel order „Opłacone” with the gift message to print → „Wysłane” → three mails to the customer in the log.

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

/** No page may scroll sideways, on a phone least of all. */
async function expectNoSideScroll(page) {
    const { scrollWidth, innerWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        innerWidth: window.innerWidth,
    }));

    expect(scrollWidth, `${page.url()} scrolls sideways`).toBeLessThanOrEqual(innerWidth);
}

/** Subjects of the mails the log mailer wrote for one recipient, decoded from MIME encoded-words. */
function mailSubjectsFor(email) {
    const decode = (subject) =>
        // Whitespace between two encoded-words is folding, not text (RFC 2047, 6.2).
        subject.replace(/\?=\s+=\?/g, '?==?').replace(/=\?utf-8\?([QB])\?([^?]*)\?=/gi, (_, encoding, text) =>
            encoding.toUpperCase() === 'B'
                ? Buffer.from(text, 'base64').toString('utf8')
                : Buffer.from(
                      text.replace(/_/g, ' ').replace(/=([0-9A-F]{2})/gi, (__, hex) => String.fromCharCode(parseInt(hex, 16))),
                      'latin1',
                  ).toString('utf8'),
        );

    // Every mail starts with its own log line; headers may fold onto the next line.
    return readFileSync(logFile, 'utf8')
        .split(/^\[\d{4}-\d{2}-\d{2} [^\]]+\] /m)
        .map((entry) => entry.replace(/\r?\n[ \t]+/g, ' '))
        .filter((entry) => new RegExp(`^To: .*${email.replace(/[.+]/g, '\\$&')}`, 'mi').test(entry))
        .map((entry) => decode(entry.match(/^Subject: (.*)$/m)?.[1] ?? ''));
}

test('a customer buys a product and Kasia ships it', async ({ page }, testInfo) => {
    const errors = watchForErrors(page);
    const email = `e2e-${testInfo.project.name}-${Date.now()}@mellow-aura.test`;

    await test.step('home page and shop', async () => {
        await page.goto('/');
        await expectNoSideScroll(page);

        await page.goto('/sklep');
        await expectNoSideScroll(page);
    });

    await test.step('add the first product in stock to the cart', async () => {
        // Vouchers go by e-mail; the test needs a parcel. Unique pieces may be sold out, so look further.
        const products = [
            ...new Set(
                await page
                    .locator('a[href*="/produkt/"]')
                    .evaluateAll((links) => links.map((link) => link.href).filter((href) => !href.includes('/produkt/voucher-'))),
            ),
        ];

        let added = false;

        for (const product of products) {
            await page.goto(product);

            const form = page.locator('#add-to-cart');

            if ((await form.count()) === 0) {
                continue;
            }

            await expectNoSideScroll(page);

            const text = page.locator('[name="custom_text"]');

            if (await text.isVisible()) {
                await text.fill('Dla Ani');
            }

            const submit = page.locator('#add-to-cart button[type="submit"], button[type="submit"][form="add-to-cart"]').first();
            const response = page.waitForResponse((r) => r.request().method() === 'POST' && new URL(r.url()).pathname === '/koszyk');

            await submit.click();

            if ((await response).ok()) {
                added = true;
                break;
            }
        }

        expect(added, 'no product in the shop could go to the cart').toBe(true);
    });

    let orderNumber;
    const giftMessage = 'Wszystkiego dobrego, Olu!\n— Marta';

    await test.step('checkout with BLIK and a parcel locker, the code typed last', async () => {
        await page.goto('/zamowienie');
        await expectNoSideScroll(page);

        const pay = page.locator('form[action$="/zamowienie"] button[type="submit"]');

        await page.locator('input[name="payment_method"][value="blik"]').check({ force: true });

        // The BLIK code lives 2 minutes: an empty form leads to the first field, never to the code (MA-218).
        await pay.click();
        await expect(page.locator('[name="email"]')).toBeFocused();
        expect(new URL(page.url()).pathname).toBe('/zamowienie');

        // The sentence stays under the field, not only in the pill that fades, until the field is corrected (MA-284).
        const emailField = page.locator('[name="email"]');
        const emailCheck = page.locator('#email-check');

        await expect(emailCheck).toHaveText('Wpisz e-mail — wyślę na niego potwierdzenie');
        await expect(emailField).toHaveAttribute('aria-invalid', 'true');
        await expect(emailField).toHaveAttribute('aria-describedby', 'email-check');
        await page.waitForTimeout(3000);
        await expect(emailCheck).toBeVisible();

        await emailField.fill(email);
        await expect(emailCheck).toHaveCount(0);
        await expect(emailField).not.toHaveAttribute('aria-invalid', 'true');
        await expect(emailField).toHaveAttribute('aria-describedby', 'email-note');
        await page.locator('[name="phone"]').fill('600100200');
        await page.locator('[name="name"]').fill('Anna Testowa');
        await page.locator('input[name="shipping_method"][value="parcel_locker"]').check({ force: true });

        const locker = page.locator('[name="locker_code"]');

        if (await locker.isVisible()) {
            await locker.fill('KRA01M');
        }

        for (const deviation of await page.locator('[data-deviation]').all()) {
            await deviation.check();
        }

        // A few words for the person who gets the gift (MA-342): folded away until asked for, counted as typed.
        const gift = page.locator('#gift_message');

        await expect(gift).toBeHidden();
        await page.getByText('Dopisz kilka słów do obdarowanej').click();
        await gift.fill(giftMessage);
        await expect(page.locator('#gift_message-count')).toHaveText(`${giftMessage.length} z 300 znaków`);

        await page.locator('[name="accept_terms"]').check();

        // Contact, delivery and terms are done, so the code is the one thing left.
        await pay.click();
        await expect(page.locator('#blik_code')).toBeFocused();
        await expect(page.locator('#blik_code-check')).toHaveText('Wpisz 6-cyfrowy kod z aplikacji banku');

        // Contact → delivery → payment, and the code right above the pay button, on a phone and a desktop alike.
        const top = async (selector) => (await page.locator(selector).first().boundingBox()).y;
        const blik = await top('#blik_code');

        expect(await top('[name="email"]')).toBeLessThan(await top('legend:has-text("Sposób dostawy")'));
        expect(await top('legend:has-text("Sposób dostawy")')).toBeLessThan(await top('legend:has-text("Płatność")'));
        expect(await top('[name="accept_terms"]')).toBeLessThan(blik);
        expect(blik).toBeLessThan(await top('form[action$="/zamowienie"] button[type="submit"]'));

        await page.locator('#blik_code').fill('123456');
        await expectNoSideScroll(page);
        await pay.click();

        await page.waitForURL('**/zamowienie/potwierdzenie');
        await expectNoSideScroll(page);

        orderNumber = (await page.locator('body').innerText()).match(/MA-\d{4}-\d{4}/)?.[0];
        expect(orderNumber, 'the confirmation shows the order number').toBeTruthy();
    });

    await test.step('the cart is empty afterwards', async () => {
        await page.goto('/zamowienie');
        expect(new URL(page.url()).pathname).not.toBe('/zamowienie/potwierdzenie');
        await expect(page.locator('form[action$="/zamowienie"]')).toHaveCount(0);
    });

    await test.step('Kasia sees the paid order in the panel and ships it', async () => {
        await page.goto('/panel');
        await page.locator('[name="email"]').fill(process.env.E2E_ADMIN_EMAIL ?? 'e2e@mellow-aura.test');
        await page.locator('[name="password"]').fill(process.env.E2E_ADMIN_PASSWORD);
        await page.getByRole('button', { name: 'Wejdź do panelu' }).click();
        await expect(page.getByRole('button', { name: 'Wejdź do panelu' })).toHaveCount(0);

        await page.goto(`/panel/zamowienia/${orderNumber}`);
        await expect(page.locator('main')).toContainText('Opłacone');
        await expectNoSideScroll(page);

        // The gift message, with its line break, and the A6 card to print it on.
        await expect(page.locator('section[aria-labelledby="gift-heading"] blockquote')).toHaveText(giftMessage);
        const card = await page.request.get(await page.getByRole('link', { name: 'Drukuj liścik' }).getAttribute('href'));

        expect(card.status()).toBe(200);
        expect(card.headers()['content-type']).toBe('application/pdf');

        const ship = page.locator('form', { has: page.locator('[name="tracking_number"]') }).first();

        await ship.locator('[name="tracking_number"]').fill('620000000000000000000000');
        await ship.getByRole('button', { name: 'Wysłane — powiadom klienta' }).click();

        await expect(page.locator('main')).toContainText('Wysłane');
        await expectNoSideScroll(page);
    });

    await test.step('three mails reach the customer', async () => {
        const subjects = mailSubjectsFor(email);

        expect(subjects).toEqual(
            expect.arrayContaining([
                `Podsumowanie zamówienia ${orderNumber}`,
                `Zamówienie ${orderNumber} jest opłacone`,
                `Zamówienie ${orderNumber} jest w drodze`,
            ]),
        );
    });

    expect(errors, 'errors in the browser or on the server').toEqual([]);
});
