/*
 * The one-screen checkout keeps the delivery cost and the pay button in step with the customer's
 * choices. The server counts everything again; these numbers are only what the screen shows.
 */
// InPost's map comes from their servers on the first opening, once per page.
let geowidget = null;
const POINT_EVENT = 'mellowauraLocker';

const loadGeowidget = ({ script, style }) => {
    geowidget ??= new Promise((resolve, reject) => {
        const link = Object.assign(document.createElement('link'), { rel: 'stylesheet', href: style });
        const tag = Object.assign(document.createElement('script'), { src: script, async: true });
        tag.addEventListener('load', () => customElements.whenDefined('inpost-geowidget').then(resolve));
        tag.addEventListener('error', () => {
            geowidget = null;
            tag.remove();
            reject(new Error('geowidget'));
        });
        document.head.append(link, tag);
    });

    return geowidget;
};

// A sentence from the page's language, with :amount filled in.
const say = (texts, key, amount = '') => (texts[key] ?? key).replace(':amount', amount);

export default ({ accepted, deviations, payment, blikCode, shipping, locker, lockerMap, subtotal, freeFrom, prices, texts }) => ({
    accepted,
    // A product with a feature nobody would expect has its own checkbox, keyed by the cart line.
    deviations: { ...deviations },
    payment,
    shipping,
    // The parcel locker's code, typed or picked on the map, and the address the map gave with it.
    locker,
    lockerAddress: '',
    blik: '',
    submitting: false,

    get shippingCost() {
        return freeFrom > 0 && subtotal >= freeFrom ? 0 : (prices[this.shipping] ?? 0);
    },

    get total() {
        return subtotal + this.shippingCost;
    },

    // The code is typed here only with BLIK Level 0; without it, on Przelewy24's page.
    get needsBlikCode() {
        return blikCode && this.payment === 'blik' && this.blik.length !== 6;
    },

    get deviationsAccepted() {
        return Object.values(this.deviations).every(Boolean);
    },

    get ready() {
        return this.accepted && this.deviationsAccepted && !this.needsBlikCode;
    },

    // What the pay button says: the amount, or what is still missing.
    get label() {
        if (this.submitting) {
            return say(texts, 'paying');
        }

        if (this.needsBlikCode) {
            return say(texts, 'blik_missing');
        }

        if (!this.deviationsAccepted) {
            return say(texts, 'deviation_missing');
        }

        return this.accepted ? say(texts, 'pay', this.$store.cart.format(this.total)) : say(texts, 'terms_missing');
    },

    init() {
        this.$el.querySelector('[aria-invalid="true"]')?.focus();

        // Geowidget v5 fires an event of this name on the document; older builds call a global function of it.
        if (lockerMap) {
            document.addEventListener(POINT_EVENT, (event) => this.pickLocker(event.detail));
            window[POINT_EVENT] = (point) => this.pickLocker(point);
        }
    },

    // The map is InPost's own element; it tells us the chosen point with an event on the document.
    async openLockerMap() {
        this.$refs.lockers.showModal();

        if (this.$refs.lockerMap.firstElementChild) {
            return;
        }

        try {
            await loadGeowidget(lockerMap);
        } catch {
            this.$refs.lockers.close();
            this.$store.cart.say(say(texts, 'locker_map_failed'));
            this.$refs.locker.focus();

            return;
        }

        const map = document.createElement('inpost-geowidget');
        map.setAttribute('token', lockerMap.token);
        map.setAttribute('language', lockerMap.language);
        // Lockers and the points where a parcel can be collected; the shop sends nothing cash on delivery.
        map.setAttribute('config', 'parcelcollect');
        map.setAttribute('onpoint', POINT_EVENT);
        this.$refs.lockerMap.append(map);
    },

    pickLocker(point) {
        if (!point?.name) {
            return;
        }

        this.locker = String(point.name).toUpperCase();
        this.lockerAddress = [point.address?.line1, point.address?.line2].filter(Boolean).join(', ');
        this.$refs.lockers.close();
        this.$refs.locker.focus();
    },

    submit(event) {
        if (this.submitting) {
            event.preventDefault();

            return;
        }

        if (this.needsBlikCode) {
            event.preventDefault();
            this.$store.cart.say(say(texts, 'blik_short'));
            this.$refs.blik.focus();

            return;
        }

        if (!this.deviationsAccepted) {
            event.preventDefault();
            this.$store.cart.say(say(texts, 'deviation_short'));
            this.$el.querySelector('[data-deviation]:not(:checked)')?.focus();

            return;
        }

        if (!this.accepted) {
            event.preventDefault();
            this.$store.cart.say(say(texts, 'terms_short'));
            this.$refs.terms.focus();

            return;
        }

        this.submitting = true;
    },
});
