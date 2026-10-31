import { readFileSync } from 'node:fs';
import { deflateSync } from 'node:zlib';
import { expect, test } from '@playwright/test';

// A custom order from the idea to the paid deposit (MA-200), in a real browser, as the customer and as Kasia:
// the form with a photo → a mail to Kasia with the photo and a copy for the customer → Kasia's quote in the panel →
// a link for half of it → the customer pays with BLIK (TestGateway pays at once) → the panel shows the link paid
// and the idea „W realizacji”, with the rest still to send.

const logFile = new URL('../../storage/logs/laravel.log', import.meta.url);

// Kasia's address from the panel, seeded by database/seeders/E2eSeeder.php.
const kasia = 'kasia-e2e@mellow-aura.test';

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

/** The mails the log mailer wrote for one recipient: subject decoded from MIME encoded-words, and the raw entry. */
function mailsFor(email) {
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
        .map((entry) => ({ subject: decode(entry.match(/^Subject: (.*)$/m)?.[1] ?? ''), entry }));
}

/**
 * A small PNG made here, in memory: a clay-coloured gradient, 96 × 64 px. No photo from outside the repository.
 */
function pngPhoto() {
    const crcTable = Array.from({ length: 256 }, (_, n) => {
        let c = n;
        for (let k = 0; k < 8; k++) {
            c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        }
        return c >>> 0;
    });
    const crc32 = (buffer) => {
        let c = 0xffffffff;
        for (const byte of buffer) {
            c = crcTable[(c ^ byte) & 0xff] ^ (c >>> 8);
        }
        return (c ^ 0xffffffff) >>> 0;
    };
    const chunk = (type, data) => {
        const length = Buffer.alloc(4);
        const crc = Buffer.alloc(4);
        const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);

        length.writeUInt32BE(data.length);
        crc.writeUInt32BE(crc32(body));

        return Buffer.concat([length, body, crc]);
    };

    const width = 96;
    const height = 64;
    const header = Buffer.alloc(13);
    header.writeUInt32BE(width, 0);
    header.writeUInt32BE(height, 4);
    header[8] = 8; // bit depth
    header[9] = 2; // RGB

    // Each row starts with filter byte 0.
    const rows = Buffer.alloc((width * 3 + 1) * height);
    for (let y = 0; y < height; y++) {
        const row = y * (width * 3 + 1);
        for (let x = 0; x < width; x++) {
            rows[row + 1 + x * 3] = 200 + Math.round((x / width) * 40);
            rows[row + 2 + x * 3] = 150 + Math.round((y / height) * 40);
            rows[row + 3 + x * 3] = 130;
        }
    }

    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        chunk('IHDR', header),
        chunk('IDAT', deflateSync(rows)),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}

/**
 * Signs in with the account serve.sh made for this screen size. The payment link's form shares Laravel's limit of
 * 10 forms a minute with the shop's checkout, counted per address for a guest; signed in, it counts per account.
 */
async function signIn(page, screen) {
    await page.goto('/panel/logowanie');
    await page.locator('[name="email"]').fill(`e2e-${screen}@mellow-aura.test`);
    await page.locator('[name="password"]').fill(process.env.E2E_ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Wejdź do panelu' }).click();
    await expect(page.getByRole('button', { name: 'Wejdź do panelu' })).toHaveCount(0);
}

test('a custom order goes from the idea with a photo to the paid deposit', async ({ page }, testInfo) => {
    const errors = watchForErrors(page);
    const screen = testInfo.project.name;
    const email = `e2e-idea-${screen}-${Date.now()}@mellow-aura.test`;
    const name = `Ola ${screen}`;

    await signIn(page, screen);

    await test.step('the customer sends her idea with a photo', async () => {
        await page.goto('/zamowienia-indywidualne');
        await expectNoSideScroll(page);

        const form = page.locator('form[action$="/zamowienia-indywidualne"]');

        await form.locator('[name="name"]').fill(name);
        await form.locator('[name="email"]').fill(email);
        await form.locator('[name="kind"]').selectOption('mugs');
        await form.locator('[name="quantity"]').fill('6');
        await form.locator('[name="description"]').fill('Sześć kubków z imionami dla rodziny, w kolorze szałwii.');
        await form.locator('input[name="photos[]"]').setInputFiles({ name: 'inspiracja.png', mimeType: 'image/png', buffer: pngPhoto() });

        // The page shows what goes up, so a wrong photo can be taken back before sending.
        await expect(form.getByRole('img', { name: 'Zdjęcie 1' })).toBeVisible();
        await expectNoSideScroll(page);

        await form.getByRole('button', { name: 'Wyślij pomysł' }).click();

        await expect(page.locator('main')).toContainText(`Mam Twój pomysł. Potwierdzenie poszło na ${email}`);
        await expectNoSideScroll(page);
    });

    await test.step('Kasia gets the idea with the photo, the customer her copy without it', async () => {
        const toKasia = mailsFor(kasia).filter((mail) => mail.subject === `Zamówienie indywidualne: Kubki z napisem — ${name}`);

        expect(toKasia, 'one mail to Kasia about the idea').toHaveLength(1);
        expect(toKasia[0].entry, 'the photo goes to Kasia as an attachment').toMatch(/filename=\S*pomysl-\d+-1\.webp/);

        const toCustomer = mailsFor(email);

        expect(toCustomer.map((mail) => mail.subject)).toEqual(['Mam Twój pomysł — odpiszę ze szkicem i wyceną']);
        expect(toCustomer[0].entry, 'the customer gets no photos back').not.toMatch(/\.webp/);
    });

    let paymentUrl;

    await test.step('Kasia sees the photo, writes her quote and sends a link for half of it', async () => {
        await page.goto('/panel/zamowienia-indywidualne', { waitUntil: 'networkidle' });

        const idea = page.locator('main article', { hasText: email });

        await expect(idea).toHaveCount(1);
        // The status is read from the select: its options hold every status's name.
        await expect(idea.locator('[name="status"]')).toHaveValue('new');

        // The photo comes from the private disk through the panel, never from a public address.
        const photo = idea.getByRole('img', { name: `Zdjęcie 1 od ${name}` });

        await expect(photo).toBeVisible();
        expect(await photo.evaluate((img) => img.naturalWidth), 'the photo loads in the panel').toBeGreaterThan(0);
        expect(new URL(await photo.getAttribute('src')).pathname).toMatch(/^\/panel\/zamowienia-indywidualne\/\d+\/zdjecia\/\d+$/);
        await expectNoSideScroll(page);

        await idea.locator('[name="quote"]').fill('300');
        await idea.getByRole('button', { name: 'Zapisz' }).click();
        await expect(page.getByText(`Zapisane. ${name}`)).toBeVisible();
        await expect(idea).toContainText('wycena 300 zł');

        await idea.getByRole('button', { name: 'Połowa — 150 zł' }).click();
        await expect(page.getByText(`Link na 150 zł poszedł do ${email}.`)).toBeVisible();
        await expect(idea.locator('[name="status"]')).toHaveValue('deposit');
        await expectNoSideScroll(page);

        paymentUrl = await idea.locator('a[href*="/zamowienia-indywidualne/platnosc/"]').getAttribute('href');
        expect(paymentUrl, 'the panel shows the link it sent').toBeTruthy();

        const linkMail = mailsFor(email).find((mail) => mail.subject === 'Link do płatności: 150 zł');

        expect(linkMail, 'the link reaches the customer by mail').toBeTruthy();
        expect(linkMail.entry).toContain(new URL(paymentUrl).pathname);
    });

    let orderNumber;

    await test.step('the customer opens the link and pays the deposit with BLIK', async () => {
        await page.goto(paymentUrl);
        await expectNoSideScroll(page);
        await expect(page.locator('main')).toContainText('Zamówienie indywidualne: Kubki z napisem');
        await expect(page.locator('main')).toContainText('Wycena 300 zł');

        await page.locator('input[name="payment_method"][value="blik"]').check({ force: true });

        const code = page.locator('#blik_code');

        if (await code.isVisible()) {
            await code.fill('123456');
        }

        await page.locator('[name="accept_terms"]').check();
        await page.getByRole('button', { name: 'Płacę 150 zł' }).click();

        await page.waitForURL('**/zamowienia-indywidualne/platnosc/potwierdzenie');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Dziękuję, zaczynam');
        await expectNoSideScroll(page);

        orderNumber = (await page.locator('main').innerText()).match(/MA-\d{4}-\d{4}/)?.[0];
        expect(orderNumber, 'the confirmation shows the payment number').toBeTruthy();

        // The link is used up: opened again it says so instead of taking the money twice.
        await page.goto(paymentUrl);
        await expect(page.locator('form[action*="/zamowienia-indywidualne/platnosc/"]')).toHaveCount(0);
    });

    await test.step('the panel shows the deposit paid and the rest still to send', async () => {
        await page.goto('/panel/zamowienia-indywidualne', { waitUntil: 'networkidle' });

        const idea = page.locator('main article', { hasText: email });

        await expect(idea.getByRole('list', { name: 'Linki do płatności' })).toContainText('Opłacony');
        // The first money in starts the work.
        await expect(idea.locator('[name="status"]')).toHaveValue('in_progress');
        await expect(idea.getByRole('button', { name: 'Całość — 150 zł' })).toBeVisible();
        await expectNoSideScroll(page);

        await page.goto(`/panel/zamowienia/${orderNumber}`);
        await expect(page.locator('main')).toContainText('Opłacone');
        await expectNoSideScroll(page);
    });

    expect(errors, 'errors in the browser or on the server').toEqual([]);
});
