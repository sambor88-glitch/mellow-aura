/*
 * Temporary tap diagnostics for MA-203: on Maciej's iPhone „Menu” and „Koszyk” answer about one tap in five,
 * and no emulator repeats it. /?debug=taps shows every touch, pointer and click in a box at the bottom, with the
 * element it landed on and whether the menu opened. Variants take parts of the page away to find the culprit:
 * &variant=pe (the header strip takes taps itself), noglass (no blur behind the pill), still (no Aura motion).
 * The choice lasts for the tab, so links keep it; /?debug=off ends it. Remove once MA-203 is closed.
 */
const KEY = 'ma-debug-taps';

export function debugVariant() {
    try {
        const param = new URLSearchParams(location.search).get('debug');
        if (param === 'off') {
            sessionStorage.removeItem(KEY);
        } else if (param === 'taps') {
            sessionStorage.setItem(KEY, new URLSearchParams(location.search).get('variant') || 'none');
        }

        return sessionStorage.getItem(KEY);
    } catch {
        return null;
    }
}

export default function debugTaps(variant) {
    const header = document.querySelector('[data-aura-header]');
    if (header && variant === 'pe') {
        header.parentElement.parentElement.style.pointerEvents = 'auto';
    }
    if (header && variant === 'noglass') {
        Object.assign(header.style, { backdropFilter: 'none', webkitBackdropFilter: 'none', background: '#f4ece2' });
    }

    const box = document.createElement('pre');
    Object.assign(box.style, {
        position: 'fixed', left: '6px', right: '6px', bottom: '6px', zIndex: 2147483647, margin: 0, maxHeight: '38vh',
        overflow: 'auto', padding: '8px', font: '11px/1.35 ui-monospace, monospace', whiteSpace: 'pre-wrap',
        background: 'rgb(0 0 0 / .82)', color: '#9f9', borderRadius: '10px', pointerEvents: 'none',
    });
    document.body.append(box);

    const t0 = performance.now();
    const lines = [];
    const log = (text) => {
        lines.unshift(`${((performance.now() - t0) / 1000).toFixed(2).padStart(7)} ${text}`);
        lines.length = Math.min(lines.length, 40);
        box.textContent = `debug=taps variant=${variant} iOS ${navigator.userAgent.match(/OS ([\d_]+)/)?.[1] ?? '?'}\n` + lines.join('\n');
    };

    const name = (el) => {
        if (! (el instanceof Element)) {
            return String(el);
        }
        const label = el.getAttribute('aria-label') || el.textContent.trim().slice(0, 14);
        return `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}.${String(el.className).split(' ')[0]} "${label}"`;
    };

    const menuOpen = () => {
        const menu = document.getElementById('menu');

        return `menu ${menu ? getComputedStyle(menu).display !== 'none' : '-'}, cart ${document.getElementById('cart')?.open ?? '-'}`;
    };

    ['touchstart', 'touchend', 'touchcancel', 'pointerdown', 'pointerup', 'pointercancel', 'mouseover', 'click'].forEach((type) => {
        document.addEventListener(type, (event) => {
            const point = event.changedTouches?.[0] ?? event;
            const hit = document.elementFromPoint(point.clientX, point.clientY);
            log(`${type.padEnd(13)} ${Math.round(point.clientX)},${Math.round(point.clientY)} → ${name(event.target)}`
                + (hit && hit !== event.target ? ` | under finger: ${name(hit)}` : '')
                + (type === 'click' ? ` | ${menuOpen()}` : ''));
            if (type === 'click') {
                setTimeout(() => log(`  +300 ms       ${menuOpen()}, scrollY ${Math.round(scrollY)}`), 300);
            }
        }, { capture: true, passive: true });
    });

    addEventListener('error', (event) => log(`JS ERROR ${event.message}`));
    addEventListener('unhandledrejection', (event) => log(`JS ERROR ${event.reason}`));
    log('ready — tap „Menu” or „Koszyk” a few times');
}
