/*
 * Google Analytics e-commerce events. Analytics runs only after a yes to statistics in the cookie banner
 * (Consent Mode, basic mode), so an event goes out only while window.gtag exists. The server writes a page's
 * events into the page (view_item, begin_checkout, purchase) and the cart's answer brings add_to_cart, so the
 * browser never guesses a price or an id.
 */
const SENT = 'mellowaura-analytics-sent';

export function track(event) {
    if (!event?.name || typeof window.gtag !== 'function') {
        return false;
    }

    window.gtag('event', event.name, event.params ?? {});

    return true;
}

function sentBefore(key) {
    try {
        return JSON.parse(localStorage.getItem(SENT) ?? '[]').includes(key);
    } catch {
        return false;
    }
}

function remember(key) {
    try {
        const keys = JSON.parse(localStorage.getItem(SENT) ?? '[]');
        localStorage.setItem(SENT, JSON.stringify([...keys, key].slice(-50)));
    } catch {
        // Without storage a reload may count the purchase again; Analytics also ties it to the order number.
    }
}

/*
 * An event for a click, e.g. workshop_booking on „Napisz na WhatsAppie”, until workshops are booked on the site.
 * The link names it in data-analytics-click; without a yes to statistics nothing goes out.
 */
export function trackClicks() {
    document.addEventListener('click', (click) => {
        const link = click.target.closest?.('[data-analytics-click]');

        if (!link) {
            return;
        }

        try {
            track(JSON.parse(link.dataset.analyticsClick));
        } catch {
            // A broken attribute never stops the link.
        }
    });
}

/*
 * Sends the events written into the page: when it loads, and again right after a yes in the banner.
 * An event marked "once", like a purchase, goes out once in this browser.
 */
export function trackPage() {
    document.querySelectorAll('script[type="application/json"][data-analytics-event]').forEach((script) => {
        if (script.dataset.sent) {
            return;
        }

        let event;

        try {
            event = JSON.parse(script.textContent);
        } catch {
            script.dataset.sent = 'invalid';

            return;
        }

        if (event.once && sentBefore(event.once)) {
            script.dataset.sent = 'before';

            return;
        }

        if (track(event)) {
            script.dataset.sent = 'now';

            if (event.once) {
                remember(event.once);
            }
        }
    });
}
