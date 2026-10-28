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

// The same checks as PlaceOrderRequest, for the fields above the BLIK code. The server checks them all again.
const PHONE = /^(\+?48)?[\s-]*(\d[\s-]*){9}$/;
const EMAIL = /^[^\s@]+@[^\s@]+$/;
const POSTAL_CODE = /^\s*\d{2}[\s-]*\d{3}\s*$/;
const LOCKER_CODE = /^[A-Z0-9-]{4,16}$/;

// The first field that would come back from the server, in the order of the screen: [field, sentence key].
const firstGap = (form, shipping) => {
    const value = (name) => (form.elements[name]?.value ?? '').trim();
    const checks = [
        ['email', () => value('email') !== ''],
        ['email', () => EMAIL.test(value('email')), 'email_format'],
        ['phone', () => value('phone') !== ''],
        ['phone', () => PHONE.test(value('phone')), 'phone_format'],
        ['name', () => value('name') !== ''],
    ];

    if (shipping === 'courier') {
        checks.push(
            ['street', () => value('street') !== ''],
            ['postal_code', () => value('postal_code') !== ''],
            ['postal_code', () => POSTAL_CODE.test(value('postal_code')), 'postal_code_format'],
            ['city', () => value('city') !== ''],
        );
    }

    if (shipping === 'parcel_locker') {
        checks.push(['locker_code', () => {
            const code = value('locker_code').replace(/\s+/g, '').toUpperCase();

            return code === '' || LOCKER_CODE.test(code);
        }, 'locker_code_format']);
    }

    const gap = checks.find(([name, passes]) => form.elements[name] && !passes());

    return gap && [form.elements[gap[0]], gap[2] ?? gap[0]];
};

// What each amount voucher pays towards the sum, in the order typed — the same split as Gifts\Support\VoucherBalance.
const splitVouchers = (vouchers, total) => {
    let left = total;

    return Object.fromEntries(vouchers.map(({ code, balance }) => {
        const amount = Math.min(left, balance);
        left -= amount;

        return [code, amount];
    }));
};

export default ({ accepted, deviations, payment, blikCode, shipping, locker, lockerMap, subtotal, vouchers = [], freeFrom, prices, texts, fieldTexts }) => ({
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

    get voucherSplit() {
        return splitVouchers(vouchers, this.total);
    },

    get vouchersPaid() {
        return Object.values(this.voucherSplit).reduce((sum, amount) => sum + amount, 0);
    },

    // What is left to pay through the gateway once the vouchers paid their part.
    get due() {
        return this.total - this.vouchersPaid;
    },

    voucherAmount(code) {
        return this.voucherSplit[code] ?? 0;
    },

    voucherLeftLabel(code) {
        const left = (vouchers.find((candidate) => candidate.code === code)?.balance ?? 0) - this.voucherAmount(code);

        return left === 0 ? say(texts, 'voucher_used_up') : say(texts, 'voucher_left', this.$store.cart.format(left));
    },

    // The whole form goes to the voucher's address, so everything typed so far comes back filled in.
    sendTo(address, extra = {}) {
        const form = this.$refs.voucherCode.form;
        Object.entries(extra).forEach(([name, value]) => form.append(Object.assign(document.createElement('input'), { type: 'hidden', name, value })));
        form.action = address;
        this.submitting = true;
        form.submit();
    },

    addVoucher() {
        if (this.submitting) {
            return;
        }

        if (this.$refs.voucherCode.value.trim() === '') {
            this.$store.cart.say(say(texts, 'voucher_empty'));
            this.$refs.voucherCode.focus();

            return;
        }

        this.sendTo(this.$refs.voucherCode.form.dataset.voucherAdd);
    },

    removeVoucher(code) {
        if (!this.submitting) {
            this.sendTo(this.$refs.voucherCode.form.dataset.voucherRemove, { remove_voucher: code });
        }
    },

    // The code is typed here only with BLIK Level 0; without it, on Przelewy24's page. Not at all when vouchers pay.
    get needsBlikCode() {
        return blikCode && this.due > 0 && this.payment === 'blik' && this.blik.length !== 6;
    },

    get deviationsAccepted() {
        return Object.values(this.deviations).every(Boolean);
    },

    get ready() {
        return this.accepted && this.deviationsAccepted && !this.needsBlikCode;
    },

    // What the pay button says: the amount, or what is still missing, in the order of the screen.
    get label() {
        if (this.submitting) {
            return say(texts, 'paying');
        }

        if (!this.deviationsAccepted) {
            return say(texts, 'deviation_missing');
        }

        if (!this.accepted) {
            return say(texts, 'terms_missing');
        }

        if (this.needsBlikCode) {
            return say(texts, 'blik_missing');
        }

        return this.due === 0 && this.vouchersPaid > 0 ? say(texts, 'pay_voucher') : say(texts, 'pay', this.$store.cart.format(this.due));
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

    // Everything above the BLIK code first: the code lives 2 minutes and is lost on any mistake the server finds.
    submit(event) {
        if (this.submitting) {
            event.preventDefault();

            return;
        }

        const gap = firstGap(this.$el, this.shipping);

        if (gap) {
            event.preventDefault();
            this.$store.cart.say(fieldTexts[gap[1]]);
            gap[0].focus();

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

        if (this.needsBlikCode) {
            event.preventDefault();
            this.$store.cart.say(say(texts, 'blik_short'));
            this.$refs.blik.focus();

            return;
        }

        this.submitting = true;
    },
});
