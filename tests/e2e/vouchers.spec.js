import { expect, test } from '@playwright/test';

// Money paths around amount vouchers and the panel (MA-263): a voucher pays the whole checkout or a part of it
// with BLIK on top (MA-211), pays a workshop booking that Kasia moves to another date (MA-210) and then cancels,
// so the money goes back on the voucher, and a paid piece gets its card with a QR code and its page /k/{token} (MA-234).
// The vouchers and the workshop dates come from database/seeders/E2eSeeder.php, a separate set for each screen size.

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

/**
 * Signs in before buying, with the account serve.sh made for this screen size. The checkout, the voucher field and
 * the booking share Laravel's limit of 10 forms a minute, counted per address for a guest: every test comes from
 * 127.0.0.1, so as guests these tests and checkout.spec.js would run into it. Signed in, they count per account.
 */
async function signIn(page, testInfo) {
    await page.goto('/panel/logowanie');
    await page.locator('[name="email"]').fill(`e2e-${testInfo.project.name}@mellow-aura.test`);
    await page.locator('[name="password"]').fill(process.env.E2E_ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Wejdź do panelu' }).click();
    await expect(page.getByRole('button', { name: 'Wejdź do panelu' })).toHaveCount(0);
}

/** A voucher of E2eSeeder for this screen size: AAA2 pays 5000 zł, AAA3 20 zł, AAA4 1000 zł. */
const voucherCode = (testInfo, suffix) => `MA-${testInfo.project.name === 'iphone' ? 'PHNE' : 'DESK'}-${suffix}`;

/** Grosze as Money::format writes them: „4740,00 zł”. */
const zl = (minor) => `${Math.trunc(minor / 100)},${String(minor % 100).padStart(2, '0')} zł`;

/** Grosze from „− 260,00 zł”, whatever the spaces. */
const grosze = (text) => {
    const [, whole, part] = text.replace(/\s/g, '').match(/(\d+),(\d{2})zł/);

    return Number(whole) * 100 + Number(part);
};

/** Text with every run of spaces (thin, non-breaking) as one plain space. */
const flat = (text) => text.replace(/\s+/g, ' ').trim();

/**
 * Puts a product that is in stock in the cart; vouchers go by e-mail, the test needs a piece. It looks from the end
 * of the shop and skips the vases, which buy-bar.spec.js needs in stock and checkout.spec.js buys from the front.
 */
async function addProductToCart(page) {
    await page.goto('/sklep');
    const products = [
        ...new Set(
            await page
                .locator('a[href*="/produkt/"]')
                .evaluateAll((links) => links.map((link) => link.href).filter((href) => !href.includes('/produkt/voucher-') && !href.endsWith('/produkt/wazony'))),
        ),
    ].reverse();

    for (const product of products) {
        await page.goto(product);

        if ((await page.locator('#add-to-cart').count()) === 0) {
            continue;
        }

        const text = page.locator('[name="custom_text"]');

        if (await text.isVisible()) {
            await text.fill('Dla Ani');
        }

        const submit = page.locator('#add-to-cart button[type="submit"], button[type="submit"][form="add-to-cart"]').first();
        const response = page.waitForResponse((r) => r.request().method() === 'POST' && new URL(r.url()).pathname === '/koszyk');

        await submit.click();

        if ((await response).ok()) {
            return;
        }
    }

    throw new Error('no product in the shop could go to the cart');
}

/** Types the code into „Masz kod vouchera?” and waits for the page to come back with it taken off the sum. */
async function applyVoucher(page, code) {
    await page.getByText('Masz kod vouchera?').click();
    await page.locator('[name="voucher_code"]').fill(code);
    await page.getByRole('button', { name: 'Użyj vouchera' }).click();
    await expect(page.getByRole('button', { name: `Nie używaj vouchera ${code}` })).toBeVisible();
}

/** The amount the voucher line takes off, read from the line „Voucher MA-…”. */
async function voucherLineAmount(page, code) {
    const line = page.locator('div', { has: page.getByRole('button', { name: `Nie używaj vouchera ${code}` }) }).last();

    return grosze(await line.locator('span.tabular-nums').innerText());
}

/** Contact, a parcel locker and the terms; the payment is left to the test. */
async function fillCheckout(page, email) {
    await page.locator('[name="email"]').fill(email);
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

    await page.locator('[name="accept_terms"]').check();
}

/** The order number on the confirmation; a voucher code (MA-DESK-AAA2) has letters, so it never matches. */
async function confirmedOrderNumber(page) {
    await page.waitForURL('**/zamowienie/potwierdzenie');
    const number = (await page.locator('main').innerText()).match(/MA-\d{4}-\d{4}/)?.[0];
    expect(number, 'the confirmation shows the order number').toBeTruthy();

    return number;
}

/** The panel's card of one voucher: „Saldo … z …” and every use of it. */
async function voucherCard(page, code) {
    await page.goto(`/panel/prezenty?kod=${code}#sprawdz-voucher`);
    const card = page.locator('div.scroll-mt-6', { hasText: /^\s*(Ważny|Wykorzystany|Wygasł)/ }).filter({ hasText: 'Saldo' });
    await expect(card).toHaveCount(1);

    return card;
}

test('a voucher pays the whole order, Kasia sees its balance and prints the card with the QR code', async ({ page }, testInfo) => {
    const errors = watchForErrors(page);
    const code = voucherCode(testInfo, 'AAA2');
    const email = `e2e-voucher-full-${testInfo.project.name}-${Date.now()}@mellow-aura.test`;
    let used;
    let orderNumber;

    await signIn(page, testInfo);

    await test.step('the voucher covers the whole checkout, no payment needed', async () => {
        await addProductToCart(page);
        await page.goto('/zamowienie');
        await fillCheckout(page, email);
        await applyVoucher(page, code);

        // The form comes back filled in, with the voucher off the sum and nothing left to pay.
        await expect(page.locator('[name="email"]')).toHaveValue(email);
        await expect(page.getByText('Voucher pokrywa całość — nic nie dopłacasz.')).toBeVisible();
        await expect(page.getByRole('group', { name: 'Płatność' })).toBeHidden();
        await expect(page.locator('#blik_code')).toBeHidden();
        used = await voucherLineAmount(page, code);
        expect(flat(await page.locator('main').innerText())).toContain(`zostanie na nim ${zl(500000 - used)}`);
        await expectNoSideScroll(page);

        const pay = page.locator('form[action$="/zamowienie"] button[type="submit"]');
        await expect(pay).toHaveText('Zamawiam, płaci voucher');
        await pay.click();

        orderNumber = await confirmedOrderNumber(page);
        expect(flat(await page.locator('main').innerText())).toContain(`Z vouchera ${zl(used)}`);
        await expectNoSideScroll(page);
    });

    await test.step('the panel shows what is left and where it went', async () => {
        const card = await voucherCard(page, code);
        expect(flat(await card.innerText())).toContain(`Saldo ${zl(500000 - used)} z ${zl(500000)}`);
        await expect(card.getByRole('listitem').filter({ hasText: orderNumber })).toContainText('wykorzystane');
    });

    await test.step('„Karta do paczki” is a PDF and the page of the piece knows nothing about the buyer', async () => {
        await page.goto(`/panel/zamowienia/${orderNumber}`);
        await expect(page.locator('main')).toContainText('Opłacone');
        await expectNoSideScroll(page);

        const cardHref = await page.getByRole('link', { name: 'Karta do paczki' }).first().getAttribute('href');
        const pdf = await page.request.get(cardHref);
        expect(pdf.status()).toBe(200);
        expect(pdf.headers()['content-type']).toContain('application/pdf');
        expect((await pdf.body()).subarray(0, 5).toString()).toBe('%PDF-');

        const pieceHref = await page.getByRole('link', { name: /Strona sztuki/ }).first().getAttribute('href');
        expect(new URL(pieceHref).pathname).toMatch(/^\/k\/[2-9a-z]{12}$/);

        // As a stranger who scanned the QR code: no panel session.
        const stranger = await page.context().browser().newContext({ ...testInfo.project.use });
        const piece = await stranger.newPage();
        const pieceErrors = watchForErrors(piece);
        const response = await piece.goto(new URL(pieceHref).pathname);

        expect(response.status()).toBe(200);
        expect(response.headers()['x-robots-tag']).toContain('noindex');
        await expect(piece.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
        await expect(piece.getByText('historia jednej sztuki')).toBeVisible();

        const html = await piece.content();
        for (const secret of [email, 'Anna Testowa', '600100200', 'KRA01M', orderNumber]) {
            expect(html, `the page of the piece shows ${secret}`).not.toContain(secret);
        }
        await expectNoSideScroll(piece);
        expect(pieceErrors).toEqual([]);
        await stranger.close();
    });

    expect(errors, 'errors in the browser or on the server').toEqual([]);
});

test('a voucher pays a part and BLIK the rest', async ({ page }, testInfo) => {
    const errors = watchForErrors(page);
    const code = voucherCode(testInfo, 'AAA3');
    const email = `e2e-voucher-part-${testInfo.project.name}-${Date.now()}@mellow-aura.test`;
    let orderNumber;

    await signIn(page, testInfo);

    await test.step('the voucher takes all of its 20 zł, BLIK pays the rest', async () => {
        await addProductToCart(page);
        await page.goto('/zamowienie');
        await fillCheckout(page, email);
        await applyVoucher(page, code);

        expect(await voucherLineAmount(page, code)).toBe(2000);
        await expect(page.getByText('cały idzie na to zamówienie')).toBeVisible();
        await expect(page.getByText('Voucher pokrywa całość — nic nie dopłacasz.')).toBeHidden();

        await page.locator('input[name="payment_method"][value="blik"]').check({ force: true });
        await page.locator('#blik_code').fill('123456');
        await expectNoSideScroll(page);

        const pay = page.locator('form[action$="/zamowienie"] button[type="submit"]');
        await expect(pay).toHaveText(/Płacę \d+,\d{2}\s*zł/);
        await pay.click();

        orderNumber = await confirmedOrderNumber(page);
        expect(flat(await page.locator('main').innerText())).toContain(`Z vouchera ${zl(2000)}`);
    });

    await test.step('the panel shows the voucher spent on this order', async () => {
        const card = await voucherCard(page, code);
        expect(flat(await card.innerText())).toContain(`Saldo ${zl(0)} z ${zl(2000)}`);
        await expect(card).toContainText('Wykorzystany');
        await expect(card.getByRole('listitem').filter({ hasText: orderNumber })).toContainText('wykorzystane');
    });

    expect(errors, 'errors in the browser or on the server').toEqual([]);
});

test('a voucher pays a workshop, Kasia moves the booking and cancels it, the money goes back', async ({ page }, testInfo) => {
    const errors = watchForErrors(page);
    const code = voucherCode(testInfo, 'AAA4');
    const email = `e2e-voucher-workshop-${testInfo.project.name}-${Date.now()}@mellow-aura.test`;
    let from;
    let to;
    let price;
    let orderNumber;

    await signIn(page, testInfo);

    await test.step('book „Rodzinnie z dzieckiem” with the voucher', async () => {
        // E2eSeeder's dates of this workshop: the first two for the desktop, the last two for the phone.
        await page.goto('/warsztaty-ceramiczne-krakow');
        const links = await page
            .locator('a[aria-label^="Wybieram: Rodzinnie z dzieckiem"]')
            .evaluateAll((anchors) => anchors.map((anchor) => new URL(anchor.href).pathname));
        expect(links).toHaveLength(4);
        [from, to] = (testInfo.project.name === 'iphone' ? links.slice(2) : links.slice(0, 2)).map((path) => path.split('/').pop());

        await page.goto(`/warsztaty-ceramiczne-krakow/zapis/${from}`);
        await page.locator('[name="name"]').fill('Ewa Zapisowa');
        await page.locator('[name="email"]').fill(email);
        await page.locator('[name="phone"]').fill('600300400');
        await page.locator('[name="accept_terms"]').check();
        await applyVoucher(page, code);

        await expect(page.locator('[name="email"]')).toHaveValue(email);
        await expect(page.getByText('Voucher pokrywa całość — nic nie dopłacasz.')).toBeVisible();
        price = await voucherLineAmount(page, code);
        expect(flat(await page.locator('main').innerText())).toContain(`zostanie na nim ${zl(100000 - price)}`);
        await expectNoSideScroll(page);

        await page.getByRole('button', { name: 'Zapisuję się, płaci voucher' }).click();
        await page.waitForURL('**/warsztaty-ceramiczne-krakow/zapis/potwierdzenie');
        await expect(page.getByRole('heading', { name: 'Do zobaczenia w pracowni' })).toBeVisible();
        orderNumber = (await page.locator('main').innerText()).match(/MA-\d{4}-\d{4}/)?.[0];
        expect(orderNumber, 'the confirmation shows the booking number').toBeTruthy();
        await expectNoSideScroll(page);
    });

    await test.step('the voucher paid the booking', async () => {
        const card = await voucherCard(page, code);
        expect(flat(await card.innerText())).toContain(`Saldo ${zl(100000 - price)} z ${zl(100000)}`);
        await expect(card.getByRole('listitem').filter({ hasText: orderNumber })).toContainText('wykorzystane');
    });

    await test.step('Kasia moves the booking to the other date, the seats follow', async () => {
        await page.goto(`/panel/warsztaty/terminy/${from}/zapisy`);
        await expect(page.locator('main')).toContainText('Zajęte 1 z 4');
        const booking = page.locator('li[id^="zapis-"]', { hasText: 'Ewa Zapisowa' });
        await expect(booking).toContainText('Potwierdzony');
        expect(flat(await booking.innerText())).toContain(`zapłacone voucherem ${zl(price)}`);
        await expectNoSideScroll(page);

        await booking.getByText('Przenieś na inny termin').click();
        await booking.locator('[name="workshop_date_id"]').selectOption(to);
        await booking.getByRole('button', { name: 'Przenieś zapis' }).click();

        await page.waitForURL(`**/panel/warsztaty/terminy/${to}/zapisy**`);
        await expect(page.locator('main')).toContainText('Zajęte 1 z 4');
        await expect(page.locator('li[id^="zapis-"]', { hasText: 'Ewa Zapisowa' })).toContainText('Przeniesiony');

        await page.goto(`/panel/warsztaty/terminy/${from}/zapisy`);
        await expect(page.locator('main')).toContainText('Zajęte 0 z 4');
    });

    await test.step('Kasia cancels it and the voucher gets its money back', async () => {
        await page.goto(`/panel/warsztaty/terminy/${to}/zapisy`);
        const booking = page.locator('li[id^="zapis-"]', { hasText: 'Ewa Zapisowa' });
        page.once('dialog', (dialog) => dialog.accept());
        await booking.getByRole('button', { name: 'Anuluj zapis' }).click();

        await expect(page.locator('main')).toContainText('Zajęte 0 z 4');
        await expect(page.getByText('Anulowane i niezapłacone (1)')).toBeVisible();

        const card = await voucherCard(page, code);
        expect(flat(await card.innerText())).toContain(`Saldo ${zl(100000)} z ${zl(100000)}`);
        await expect(card.getByRole('listitem').filter({ hasText: orderNumber })).toContainText('wróciło na voucher');
    });

    expect(errors, 'errors in the browser or on the server').toEqual([]);
});
