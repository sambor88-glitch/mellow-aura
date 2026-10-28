/*
 * Moving between pages (MA-237). Chromium renders the next page ahead of a click (<x-shared::speculation-rules>)
 * and fades one page into the other (<x-shared::page-transitions>, app.css). Here only what CSS cannot do:
 * the photo of the clicked card grows into the product photo, and back. Browsers without these events
 * change pages the usual way.
 */
const PHOTO = 'pvimg';

// The photo of the card that links to `url`. The product page names its own photo in app.css.
function photoFor(url) {
    if (!url) {
        return null;
    }

    const { pathname } = new URL(url, location.href);
    const card = [...document.querySelectorAll('a[href]')].find((link) => link.pathname === pathname && link.querySelector('[data-vt-photo]'));

    return card?.querySelector('[data-vt-photo]') ?? null;
}

// One name on a page: two would cancel the transition, so the photo of this page gives way to the card's.
function name(photo) {
    document.querySelectorAll('[data-vt-photo]').forEach((element) => {
        element.style.viewTransitionName = element === photo ? PHOTO : 'none';
    });
}

function clear() {
    document.querySelectorAll('[data-vt-photo]').forEach((element) => (element.style.viewTransitionName = ''));
}

export function pageTransitions() {
    addEventListener('pageswap', (event) => {
        if (!event.viewTransition) {
            return;
        }

        const photo = photoFor(event.activation?.entry?.url);
        if (photo) {
            name(photo);
            // Back from the next page through the history cache, this page is shown again as it was.
            event.viewTransition.finished.then(clear, clear);
        }
    });

    // On a page loaded the usual way this script may come after the event; the photo then fades with the page.
    addEventListener('pagereveal', (event) => {
        if (!event.viewTransition) {
            return;
        }

        const photo = photoFor(window.navigation?.activation?.from?.url);
        if (photo) {
            name(photo);
            event.viewTransition.finished.then(clear, clear);
        }
    });
}

// Runs `callback` once the page is on screen: at once, or when a page rendered ahead of a click is shown.
export function whenShown(callback) {
    if (document.prerendering) {
        document.addEventListener('prerenderingchange', callback, { once: true });
    } else {
        callback();
    }
}

/*
 * Pages rendered ahead keep what they read from the server, the cart count too. After the cart changes, the rules
 * go out and come back: the browser drops the old pages and renders the next one afresh.
 */
export function renewSpeculations() {
    const rules = document.querySelector('script[data-speculation-rules]');
    if (!rules) {
        return;
    }

    const fresh = document.createElement('script');
    fresh.type = 'speculationrules';
    fresh.dataset.speculationRules = '';
    fresh.textContent = rules.textContent;
    rules.remove();
    document.head.append(fresh);
}
