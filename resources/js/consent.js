import { trackPage } from './analytics';

/*
 * The cookie banner. The server decides whether it shows and whether Google Analytics loads with the page;
 * this saves a choice in the background and starts Analytics right after a yes, the same way
 * the consent::head view does (Consent Mode, basic mode). Without JavaScript the form posts normally.
 */
function startAnalytics(measurementId) {
    if (window.gtag) {
        return;
    }

    window.dataLayer = window.dataLayer || [];
    window.gtag = function gtag() {
        window.dataLayer.push(arguments);
    };
    window.gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied' });
    window.gtag('consent', 'update', { analytics_storage: 'granted' });
    window.gtag('js', new Date());
    window.gtag('config', measurementId);

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    document.head.appendChild(script);
}

export default (config) => ({
    open: config.open,
    decided: config.decided,
    analytics: config.analytics,
    busy: false,
    fallback: false,

    init() {
        // The footer's „Ustawienia cookies” opens the banner here instead of leaving the page.
        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-consent-open]')) {
                event.preventDefault();
                this.open = true;
                // x-show brings an element back in the next animation frame, so focus waits until after it.
                requestAnimationFrame(() => setTimeout(() => this.$el.querySelector('button')?.focus()));
            }
        });
    },

    async choose(event) {
        if (this.fallback) {
            return;
        }

        event.preventDefault();

        if (this.busy) {
            return;
        }

        const allow = event.submitter?.value === '1';
        const data = new FormData(event.target);
        data.set('analytics', allow ? '1' : '0');
        this.busy = true;

        try {
            const response = await fetch(event.target.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } });

            if (!response.ok) {
                throw new Error(`Consent not saved: ${response.status}`);
            }
        } catch {
            // Let the browser post the form the usual way; the page reloads with the choice saved.
            this.fallback = true;
            event.submitter?.click();

            return;
        }

        const wasAllowed = this.analytics;
        this.busy = false;
        this.decided = true;
        this.analytics = allow;
        this.open = false;

        if (allow) {
            startAnalytics(config.measurementId);
            // The page's own events (a viewed product, a purchase) go out now too.
            trackPage();
        } else if (wasAllowed) {
            // Analytics already runs on this page; a reload leaves it out.
            window.location.reload();
        }
    },
});
