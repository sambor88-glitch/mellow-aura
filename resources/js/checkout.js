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
        const code = () => value('locker_code').replace(/\s+/g, '').toUpperCase();

        checks.push(
            ['locker_code', () => code() !== ''],
            ['locker_code', () => LOCKER_CODE.test(code()), 'locker_code_format'],
        );
    }

    const gap = checks.find(([name, passes]) => form.elements[name] && !passes());

    return gap && [form.elements[gap[0]], gap[2] ?? gap[0]];
};

// What a check on this screen says, under the field, where x-shared::field puts the server's sentence.
// It stays until the field is corrected; the pill alone vanishes after 2.6 s and hides under the phone's keyboard.
const checkId = (field) => `${field.id || field.name.replace(/\W+/g, '-')}-check`;

// The field was corrected some other way than typing, e.g. a locker picked on the map.
const unflag = (field) => document.getElementById(checkId(field))?.dispatchEvent(new Event('fixed'));

const flag = (field, message) => {
    unflag(field);

    const id = checkId(field);
    const checkbox = field.type === 'checkbox';
    const corrected = checkbox ? 'change' : 'input';
    const before = { describedby: field.getAttribute('aria-describedby'), invalid: field.getAttribute('aria-invalid') };
    // The hint or the server's own sentence gives way to this one, as the hint gives way to the server's.
    const note = before.describedby && document.getElementById(before.describedby);
    // A field the server already marked keeps its red border; the others swap theirs for it.
    const borders = checkbox ? [] : [...field.classList].filter((name) => name.startsWith('border-') && name.endsWith('line'));
    const error = Object.assign(document.createElement('p'), { id, className: 'mt-1.5 text-[13px] text-error', textContent: message });

    note?.setAttribute('hidden', '');
    (checkbox ? field.closest('label') : field).after(error);
    field.setAttribute('aria-describedby', id);
    field.setAttribute('aria-invalid', 'true');

    if (borders.length) {
        field.classList.replace(borders[0], 'border-error');
    }

    const fixed = () => {
        field.removeEventListener(corrected, fixed);
        error.remove();
        note?.removeAttribute('hidden');
        before.describedby ? field.setAttribute('aria-describedby', before.describedby) : field.removeAttribute('aria-describedby');
        before.invalid ? field.setAttribute('aria-invalid', before.invalid) : field.removeAttribute('aria-invalid');

        if (borders.length) {
            field.classList.replace('border-error', borders[0]);
        }
    };

    error.addEventListener('fixed', fixed, { once: true });
    field.addEventListener(corrected, fixed);
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
            flag(this.$refs.voucherCode, say(texts, 'voucher_empty'));
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

    // Without the locker's code Kasia has nowhere to send the parcel (MA-248).
    get needsLocker() {
        return this.shipping === 'parcel_locker' && String(this.locker ?? '').trim() === '';
    },

    get ready() {
        return this.accepted && this.deviationsAccepted && !this.needsBlikCode && !this.needsLocker;
    },

    // What the pay button says: the amount, or what is still missing, in the order of the screen.
    get label() {
        if (this.submitting) {
            return say(texts, 'paying');
        }

        if (this.needsLocker) {
            return say(texts, 'locker_missing');
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
        unflag(this.$refs.locker);
        this.$refs.locker.focus();
    },

    // Everything above the BLIK code first: the code lives 2 minutes and is lost on any mistake the server finds.
    submit(event) {
        if (this.submitting) {
            event.preventDefault();

            return;
        }

        const gap = firstGap(this.$el, this.shipping);
        const stop = (field, message) => {
            event.preventDefault();
            this.$store.cart.say(message);
            flag(field, message);
            field.focus();
        };

        if (gap) {
            stop(gap[0], fieldTexts[gap[1]]);

            return;
        }

        if (!this.deviationsAccepted) {
            stop(this.$el.querySelector('[data-deviation]:not(:checked)'), say(texts, 'deviation_short'));

            return;
        }

        if (!this.accepted) {
            stop(this.$refs.terms, say(texts, 'terms_short'));

            return;
        }

        if (this.needsBlikCode) {
            stop(this.$refs.blik, say(texts, 'blik_short'));

            return;
        }

        this.submitting = true;
    },
});
