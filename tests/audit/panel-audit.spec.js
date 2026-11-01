import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

// Every panel screen on a phone (MA-335): the browser walks /panel/* from the menu and the links on each screen,
// opens every tab and every folded list, and checks what a person sees at 390 and 360 px. Findings and full-page
// screenshots land in audit-results/. Run after tests/e2e have filled the shop: .github/workflows/panel-audit.yml.

const OUT = 'audit-results';
const VIEWPORTS = [
    { name: '390', width: 390, height: 844 },
    { name: '360', width: 360, height: 780 },
];
// Files, sign-out and photos are not screens.
const SKIP = [/\/wyloguj/, /\/karta$/, /\/certyfikaty$/, /\/etykieta/, /\/zdjecia\/\d+/, /\/manifest/, /\/sw\.js/, /\/eksport/, /\.(pdf|csv|zip|png|jpe?g|webp)$/];
const START = [
    '/panel', '/panel/certyfikat', '/panel/konta', '/panel/szukaj', '/panel/kategorie', '/panel/produkty', '/panel/produkty/nowy',
    '/panel/produkty/ustawienia', '/panel/odstapienia', '/panel/reklamacje', '/panel/zamowienia', '/panel/tresci',
    '/panel/uslugi', '/panel/zdjecia-stron', '/panel/zamowienia-indywidualne', '/panel/klienci', '/panel/wypaly',
    '/panel/wypaly/zgloszenia', '/panel/karty-wyboru', '/panel/prezenty', '/panel/niewyslane-maile',
    '/panel/kubek-z-napisem', '/panel/google', '/panel/maile', '/panel/ustawienia', '/panel/warsztaty',
];
const MAX_PAGES = 90;

// One screen per pattern: /panel/zamowienia/MA-2026-1001 and …/1002 look the same. Filters (?status=…) count
// as their own screen, a few per page, because an empty list is a screen of its own.
function patternOf(url) {
    const u = new URL(url);
    const p = u.pathname.replace(/MA-\d{4}-\d+/g, ':order').replace(/\/\d+(?=\/|$)/g, '/:id');
    const keys = [...u.searchParams.keys()].filter((k) => k !== 'tab').sort();
    return keys.length ? `${p}?${keys.map((k) => `${k}=${u.searchParams.get(k)}`).join('&')}` : p;
}

function slug(text) {
    return text.replace(/^https?:\/\/[^/]+/, '').replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'root';
}

async function signIn(page) {
    await page.goto('/panel/logowanie');
    await page.locator('[name="email"]').fill(process.env.E2E_ADMIN_EMAIL ?? 'e2e@mellow-aura.test');
    await page.locator('[name="password"]').fill(process.env.E2E_ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Wejdź do panelu' }).click();
    await expect(page.getByRole('button', { name: 'Wejdź do panelu' })).toHaveCount(0);
}

// Runs in the page. Returns findings: { kind, severity, where, text, detail }.
function inspect() {
    const vw = document.documentElement.clientWidth;
    const findings = [];
    const seen = new Set();
    const visible = (el) => {
        if (!(el instanceof Element)) return false;
        const s = getComputedStyle(el);
        if (s.display === 'none' || s.visibility === 'hidden' || Number(s.opacity) === 0) return false;
        const r = el.getBoundingClientRect();
        if (r.width < 1 || r.height < 1) return false;
        // Screen-reader-only text.
        if (r.width <= 1 && r.height <= 1) return false;
        if (s.position === 'absolute' && s.clip && s.clip !== 'auto' && s.clip.startsWith('rect(0')) return false;
        for (let a = el; a; a = a.parentElement) {
            if (a.hasAttribute('hidden') || a.getAttribute('aria-hidden') === 'true') return false;
            const as = getComputedStyle(a);
            if (as.display === 'none' || as.visibility === 'hidden') return false;
            if (a.tagName === 'DETAILS' && !a.open && a !== el && !el.closest('summary')) return false;
        }
        return true;
    };
    const describe = (el) => {
        const tag = el.tagName.toLowerCase();
        const id = el.id ? `#${el.id}` : '';
        const name = el.getAttribute('name') ? `[name=${el.getAttribute('name')}]` : '';
        const label = (el.getAttribute('aria-label') || el.innerText || el.value || el.getAttribute('placeholder') || '')
            .replace(/\s+/g, ' ').trim().slice(0, 70);
        const heading = (() => {
            for (let a = el; a; a = a.parentElement) {
                let sib = a.previousElementSibling;
                while (sib) {
                    if (/^H[1-4]$/.test(sib.tagName)) return sib.innerText.trim().slice(0, 40);
                    sib = sib.previousElementSibling;
                }
            }
            return '';
        })();
        return { where: `${tag}${id}${name}`, text: label, section: heading };
    };
    const add = (kind, severity, el, detail) => {
        const d = describe(el);
        const key = `${kind}|${d.where}|${d.text}|${detail}`;
        if (seen.has(key)) return;
        seen.add(key);
        const r = el.getBoundingClientRect();
        findings.push({ kind, severity, ...d, detail, y: Math.round(r.top + scrollY) });
    };
    const clipsX = (el) => {
        for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) {
            const ox = getComputedStyle(a).overflowX;
            if (ox === 'auto' || ox === 'scroll') return a;
        }
        return null;
    };

    // 1. The page scrolls sideways.
    if (document.documentElement.scrollWidth > vw + 1) {
        findings.push({ kind: 'side-scroll', severity: 'critical', where: 'html', text: '', section: '', detail: `scrollWidth ${document.documentElement.scrollWidth} > ${vw}`, y: 0 });
    }

    const all = [...document.querySelectorAll('body *')].filter((el) => !['SCRIPT', 'STYLE', 'TEMPLATE', 'svg', 'path', 'NOSCRIPT'].includes(el.tagName) && visible(el));

    // 2. Something sticks out of the screen or out of its box.
    for (const el of all) {
        const r = el.getBoundingClientRect();
        const scroller = clipsX(el);
        if (!scroller && (r.right > vw + 1 || r.left < -1) && getComputedStyle(el).position !== 'fixed') {
            add('off-screen', 'critical', el, `left ${Math.round(r.left)}, right ${Math.round(r.right)} of ${vw}`);
        }
        if (el.matches('input, select, textarea, button, a[class*="rounded"], [role="tab"]')) {
            const parent = el.parentElement;
            if (parent && !scroller) {
                const pr = parent.getBoundingClientRect();
                const ps = getComputedStyle(parent);
                const inner = pr.right - parseFloat(ps.paddingRight) - parseFloat(ps.borderRightWidth);
                if (r.right > inner + 1.5 && r.right > pr.right - 0.5 + 1) {
                    add('out-of-box', 'major', el, `ends ${Math.round(r.right - pr.right)} px past its box`);
                }
            }
        }
    }

    // 3. Cut text: an element narrower than its text with overflow hidden or an ellipsis.
    for (const el of all) {
        const s = getComputedStyle(el);
        if ((s.textOverflow === 'ellipsis' || s.overflowX === 'hidden' || s.overflow === 'hidden') && el.scrollWidth > el.clientWidth + 1 && el.innerText && el.innerText.trim().length > 0 && el.children.length === 0) {
            add('truncated', 'minor', el, `shows ${el.clientWidth} of ${el.scrollWidth} px: „${el.innerText.trim().slice(0, 90)}”`);
        }
    }

    // 4. Touch targets below 44 px, controls closer than 16 px to the screen edge.
    const targets = all.filter((el) => el.matches('a[href], button, select, input:not([type=hidden]), textarea, summary, [role="tab"], [role="button"]'));
    for (const el of targets) {
        const r = el.getBoundingClientRect();
        const inline = el.tagName === 'A' && getComputedStyle(el).display === 'inline' && el.closest('p, li, label, span');
        const box = el.matches('input[type=checkbox], input[type=radio]') ? (el.closest('label')?.getBoundingClientRect() ?? r) : r;
        if (inline) {
            if (box.height < 24) add('small-target', 'minor', el, `inline link ${Math.round(box.width)}×${Math.round(box.height)} px`);
        } else if (box.height < 43.5 || box.width < 43.5) {
            add('small-target', box.height < 32 || box.width < 32 ? 'major' : 'minor', el, `${Math.round(box.width)}×${Math.round(box.height)} px (min 44×44)`);
        }
        if (!clipsX(el) && (r.left < 15.5 || vw - r.right < 15.5) && getComputedStyle(el).position !== 'fixed' && r.width < vw - 20) {
            add('edge', 'minor', el, `${Math.round(Math.min(r.left, vw - r.right))} px from the screen edge (min 16)`);
        }
    }
    // Neighbours in one row touching: under 8 px between two targets.
    for (let i = 0; i < targets.length; i++) {
        const a = targets[i].getBoundingClientRect();
        for (let j = i + 1; j < Math.min(targets.length, i + 6); j++) {
            if (targets[j].contains(targets[i]) || targets[i].contains(targets[j])) continue;
            const b = targets[j].getBoundingClientRect();
            const sameRow = Math.abs((a.top + a.bottom) / 2 - (b.top + b.bottom) / 2) < Math.min(a.height, b.height) / 2;
            const gapX = Math.max(b.left - a.right, a.left - b.right);
            const sameCol = Math.max(a.left, b.left) < Math.min(a.right, b.right);
            const gapY = Math.max(b.top - a.bottom, a.top - b.bottom);
            if ((sameRow && gapX >= -0.5 && gapX < 7.5) || (sameCol && !sameRow && gapY >= -0.5 && gapY < 3.5)) {
                add('cramped', 'minor', targets[j], `${Math.round(sameRow ? gapX : gapY)} px from „${describe(targets[i]).text}”`);
            }
        }
    }

    // 5. Fields under 16 px: Safari zooms the page in. Only meaningful where the browser reports a touch screen,
    // since the panel raises fields to 16 px under (pointer: coarse).
    if (matchMedia('(pointer: coarse)').matches) for (const el of all.filter((e) => e.matches('input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=file]), select, textarea'))) {
        const fs = parseFloat(getComputedStyle(el).fontSize);
        if (fs < 16) add('zoom-on-focus', 'major', el, `font ${fs}px (iPhone zooms below 16)`);
    }

    // 6. One row, different heights: controls side by side should match.
    const controls = all.filter((el) => el.matches('input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=file]), select, button, a[class*="rounded-full"], [role="tab"]'));
    const byParent = new Map();
    for (const el of controls) {
        const row = el.parentElement;
        if (!byParent.has(row)) byParent.set(row, []);
        byParent.get(row).push(el);
    }
    for (const [, group] of byParent) {
        if (group.length < 2) continue;
        const rects = group.map((el) => [el, el.getBoundingClientRect()]);
        for (let i = 0; i < rects.length; i++) {
            for (let j = i + 1; j < rects.length; j++) {
                const [ea, a] = rects[i];
                const [eb, b] = rects[j];
                const sameRow = Math.abs(a.top - b.top) < 4 || Math.abs(a.bottom - b.bottom) < 4 || Math.abs((a.top + a.bottom) / 2 - (b.top + b.bottom) / 2) < 6;
                if (sameRow && Math.abs(a.height - b.height) > 2.5) {
                    add('uneven-height', 'major', eb, `${Math.round(b.height)} px next to „${describe(ea).text}” ${Math.round(a.height)} px`);
                }
            }
        }
        // Pills that wrap into ragged rows: same container, several rows, different widths.
        const pills = rects.filter(([el]) => el.matches('a[class*="rounded-full"], button[class*="rounded-full"], [role="tab"]'));
        if (pills.length >= 3) {
            const rows = [...new Set(pills.map(([, r]) => Math.round(r.top)))];
            const widths = pills.map(([, r]) => Math.round(r.width));
            const holder = pills[0][0].parentElement;
            const scroller = clipsX(pills[0][0]) || (getComputedStyle(holder).overflowX !== 'visible' ? holder : null);
            // A grid of equal columns (pill-grid) is even by design, its odd last pill spans the row.
            const grid = getComputedStyle(holder).display === 'grid';
            if (!scroller && !grid && rows.length > 1 && Math.max(...widths) - Math.min(...widths) > 12) {
                add('ragged-pills', 'major', pills[0][0].parentElement, `${pills.length} pills in ${rows.length} rows, widths ${Math.min(...widths)}–${Math.max(...widths)} px`);
            }
        }
    }

    // 7. Fields stacked in one column with different widths.
    for (const form of document.querySelectorAll('form, fieldset, section')) {
        const fields = [...form.querySelectorAll('input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=file]):not([type=color]), select, textarea')].filter(visible);
        const byLeft = new Map();
        for (const f of fields) {
            const r = f.getBoundingClientRect();
            const key = Math.round(r.left);
            if (!byLeft.has(key)) byLeft.set(key, []);
            byLeft.get(key).push([f, r]);
        }
        for (const [, col] of byLeft) {
            const alone = col.filter(([f, r]) => !fields.some((o) => o !== f && Math.abs(o.getBoundingClientRect().top - r.top) < 4));
            if (alone.length < 2) continue;
            const widths = alone.map(([, r]) => Math.round(r.width));
            const max = Math.max(...widths);
            for (const [f, r] of alone) {
                if (max - r.width > 6 && r.width > max * 0.6) {
                    add('uneven-width', 'major', f, `${Math.round(r.width)} px, the field under/over it ${max} px`);
                }
            }
        }
    }

    // 8. Button and pill labels breaking into two lines.
    // Lines of visible text only: screen-reader text and stacked blocks (a count under a label) do not count.
    const lines = (el) => {
        const tops = new Set();
        const texts = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
        let text;
        while ((text = texts.nextNode())) {
            const holder = text.parentElement;
            if (!text.textContent.trim() || !visible(holder) || holder.getBoundingClientRect().width <= 1) continue;
            if (holder !== el && getComputedStyle(holder).display === 'block') continue;
            const range = document.createRange();
            range.selectNodeContents(text);
            [...range.getClientRects()].filter((q) => q.width > 1).forEach((q) => tops.add(Math.round(q.top / 4)));
        }
        return tops.size;
    };
    for (const el of all.filter((e) => e.matches('button, a[class*="rounded-full"], [role="tab"], summary, a[class*="bg-ink"]'))) {
        if ((el.innerText || '').trim().length > 0 && lines(el) > 1) add('wrapped-label', 'major', el, 'label breaks into two lines');
    }

    // 9. Lonely one-letter words at the end of a line (Polish typography: „i”, „w”, „z” move to the next line).
    const walker = document.createTreeWalker(document.querySelector('main') ?? document.body, NodeFilter.SHOW_TEXT);
    let node;
    let orphans = 0;
    while ((node = walker.nextNode()) && orphans < 40) {
        const parent = node.parentElement;
        if (!parent || !visible(parent) || parent.closest('input, textarea, select, script, style')) continue;
        const text = node.textContent;
        const re = /(^|\s)([aiouwzAIOUWZ])\s/g;
        let m;
        while ((m = re.exec(text))) {
            const start = m.index + m[1].length;
            const r1 = document.createRange();
            r1.setStart(node, start);
            r1.setEnd(node, start + 1);
            const r2 = document.createRange();
            const next = text.slice(start + 2).search(/\S/);
            if (next < 0) continue;
            r2.setStart(node, start + 2 + next);
            r2.setEnd(node, start + 3 + next);
            const a = r1.getClientRects()[0];
            const b = r2.getClientRects()[0];
            if (a && b && b.top > a.top + 2) {
                orphans++;
                add('orphan', 'minor', parent, `„${m[2]}” left at the end of a line: „…${text.slice(Math.max(0, start - 25), start + 25).replace(/\s+/g, ' ')}…”`);
            }
        }
    }

    // 10. Siblings overlapping each other.
    for (const el of all) {
        const kids = [...el.children].filter(visible).filter((k) => !['absolute', 'fixed'].includes(getComputedStyle(k).position));
        for (let i = 0; i < kids.length; i++) {
            const a = kids[i].getBoundingClientRect();
            for (let j = i + 1; j < Math.min(kids.length, i + 4); j++) {
                const b = kids[j].getBoundingClientRect();
                const w = Math.min(a.right, b.right) - Math.max(a.left, b.left);
                const h = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
                if (w > 3 && h > 3) add('overlap', 'major', kids[j], `overlaps „${describe(kids[i]).text}” by ${Math.round(w)}×${Math.round(h)} px`);
            }
        }
    }

    // 11. Text too small to read on a phone.
    for (const el of all) {
        if (![...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim().length > 2)) continue;
        const size = parseFloat(getComputedStyle(el).fontSize);
        if (size < 11.5) add('tiny-text', 'minor', el, `${size}px`);
    }

    // 12. Images without a description, broken images.
    for (const img of all.filter((e) => e.tagName === 'IMG')) {
        if (!img.hasAttribute('alt')) add('img-alt', 'minor', img, 'no alt');
        if (img.complete && img.naturalWidth === 0) add('img-broken', 'major', img, img.getAttribute('src'));
    }

    return findings;
}

test('every panel screen on a phone', async ({ browser }) => {
    test.setTimeout(45 * 60_000);
    fs.mkdirSync(`${OUT}/shots`, { recursive: true });

    const context = await browser.newContext({ isMobile: true, hasTouch: true, deviceScaleFactor: 2, viewport: { width: 390, height: 844 }, locale: 'pl-PL' });
    const page = await context.newPage();
    // A phone has a coarse pointer and no hover; some Chromium builds do not say so for an emulated touch screen.
    const cdp = await context.newCDPSession(page);
    await cdp.send('Emulation.setEmulatedMedia', { features: [{ name: 'pointer', value: 'coarse' }, { name: 'hover', value: 'none' }] }).catch(() => {});
    const errors = [];
    page.on('pageerror', (e) => errors.push({ url: page.url(), error: String(e) }));
    page.on('console', (m) => m.type() === 'error' && errors.push({ url: page.url(), error: m.text() }));
    page.on('response', (r) => r.status() >= 500 && errors.push({ url: r.url(), error: `HTTP ${r.status()}` }));

    // The sign-in screen first, as a guest.
    const report = [];
    const capture = async (key, label) => {
        for (const vp of VIEWPORTS) {
            await page.setViewportSize({ width: vp.width, height: vp.height });
            await page.waitForTimeout(150);
            const findings = await page.evaluate(inspect);
            const shot = `shots/${key}__${vp.name}.png`;
            if (vp.name === '390' || findings.some((f) => f.severity !== 'minor')) {
                await page.screenshot({ path: `${OUT}/${shot}`, fullPage: true });
            }
            report.push({ screen: label, url: page.url().replace(/^https?:\/\/[^/]+/, ''), viewport: vp.name, shot, findings });
        }
        await page.setViewportSize({ width: 390, height: 844 });
    };

    await page.goto('/panel/logowanie', { waitUntil: 'networkidle' });
    const touch = await page.evaluate(() => matchMedia('(pointer: coarse)').matches);
    console.log(`pointer: coarse = ${touch}${touch ? '' : ' — the 16 px field check is skipped'}`);
    await capture('logowanie', 'Logowanie');
    await signIn(page);

    const queue = START.map((p) => new URL(p, page.url()).href);
    const done = new Set();
    const variants = new Map();
    let pages = 0;

    while (queue.length && pages < MAX_PAGES) {
        const url = queue.shift();
        const u = new URL(url);
        if (!u.pathname.startsWith('/panel') || SKIP.some((re) => re.test(u.pathname))) continue;
        const pattern = patternOf(url);
        if (done.has(pattern)) continue;
        const base = pattern.split('?')[0];
        if (pattern.includes('?')) {
            const n = variants.get(base) ?? 0;
            if (n >= 4) continue;
            variants.set(base, n + 1);
        }
        done.add(pattern);

        const response = await page.goto(url, { waitUntil: 'networkidle' }).catch(() => null);
        if (!response || !(response.headers()['content-type'] ?? '').includes('text/html')) continue;
        if (new URL(page.url()).pathname === '/panel/logowanie') await signIn(page);
        pages++;

        const title = (await page.title()).replace(/ ·.*$/, '');
        const key = slug(pattern);
        await capture(key, title);

        // Every tab of the screen.
        const tabs = page.locator('[role="tab"]');
        const tabCount = await tabs.count();
        for (let i = 1; i < tabCount; i++) {
            const tab = tabs.nth(i);
            if (!(await tab.isVisible())) continue;
            const name = ((await tab.innerText()) || `tab${i}`).replace(/\s+\d+$/, '').trim();
            await tab.click();
            await page.waitForTimeout(200);
            await capture(`${key}__tab_${slug(name)}`, `${title} → ${name}`);
        }
        if (tabCount > 1) await tabs.first().click();

        // Folded lists opened: what is inside a <details> is a screen too.
        const folded = await page.locator('main details:not([open])').count();
        if (folded > 0) {
            await page.evaluate(() => document.querySelectorAll('main details').forEach((d) => (d.open = true)));
            for (let i = 0; i < Math.max(tabCount, 1); i++) {
                if (tabCount > 1) {
                    const tab = tabs.nth(i);
                    if (!(await tab.isVisible())) continue;
                    await tab.click();
                    await page.waitForTimeout(150);
                }
                const name = tabCount > 1 ? ((await tabs.nth(i).innerText()) || `tab${i}`).replace(/\s+\d+$/, '').trim() : '';
                await capture(`${key}__open${name ? `_${slug(name)}` : ''}`, `${title}${name ? ` → ${name}` : ''} (rozwinięte)`);
            }
        }

        // Follow links to more panel screens.
        const links = await page.$$eval('a[href]', (as) => as.map((a) => a.href));
        for (const href of links) {
            const l = new URL(href, page.url());
            l.hash = '';
            if (l.origin === new URL(page.url()).origin && l.pathname.startsWith('/panel') && !done.has(patternOf(l.href))) queue.push(l.href);
        }
    }

    // The menu and the search, open on a phone.
    await page.goto('/panel/zamowienia', { waitUntil: 'networkidle' });
    await page.locator('[aria-controls="panel-menu"]').click();
    await page.waitForTimeout(300);
    await capture('menu_otwarte', 'Menu (otwarte)');
    await page.keyboard.press('Escape');
    const search = page.locator('button[aria-label*="Szukaj"], a[aria-label*="Szukaj"]').first();
    if (await search.count()) {
        await search.click();
        await page.waitForTimeout(300);
        await capture('szukaj_otwarte', 'Wyszukiwarka (otwarta)');
        await page.keyboard.type('a');
        await page.waitForTimeout(800);
        await capture('szukaj_wyniki', 'Wyszukiwarka (wyniki)');
    }

    fs.writeFileSync(`${OUT}/report.json`, JSON.stringify({ pages, touch, errors, report }, null, 1));

    // A readable summary in the log: counts per kind, then every finding that is not minor.
    const flat = report.flatMap((r) => r.findings.map((f) => ({ ...f, screen: r.screen, viewport: r.viewport, url: r.url })));
    const counts = {};
    for (const f of flat) counts[`${f.severity} ${f.kind}`] = (counts[`${f.severity} ${f.kind}`] ?? 0) + 1;
    const lines = [`Screens: ${pages}, captures: ${report.length}, findings: ${flat.length}, JS/HTTP errors: ${errors.length}, pointer coarse: ${touch}`, ''];
    for (const [k, n] of Object.entries(counts).sort()) lines.push(`${String(n).padStart(4)}  ${k}`);
    fs.writeFileSync(`${OUT}/summary.txt`, `${lines.join('\n')}\n`);
    console.log(lines.join('\n'));
});
