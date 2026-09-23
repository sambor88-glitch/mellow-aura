/*
 * The one-screen checkout keeps the delivery cost and the pay button in step with the customer's
 * choices. The server counts everything again; these numbers are only what the screen shows.
 */
// Stripe's objects stay outside Alpine: a reactive proxy around the card field breaks it.
let stripe = null;
let card = null;

// A sentence from the page's language, with :amount filled in.
const say = (texts, key, amount = '') => (texts[key] ?? key).replace(':amount', amount);

export default ({ accepted, deviations, payment, shipping, subtotal, freeFrom, prices, stripeKey, texts }) => ({
    accepted,
    // A product with a feature nobody would expect has its own checkbox, keyed by the cart line.
    deviations: { ...deviations },
    payment,
    shipping,
    blik: '',
    cardComplete: false,
    submitting: false,

    get shippingCost() {
        return freeFrom > 0 && subtotal >= freeFrom ? 0 : (prices[this.shipping] ?? 0);
    },

    get total() {
        return subtotal + this.shippingCost;
    },

    // With keys, the code and the card go from this page straight to Stripe and never through our server.
    // A bank transfer has nothing to confirm, so it posts the form like before.
    get confirmsInBrowser() {
        return Boolean(stripeKey) && this.payment !== 'bank_transfer';
    },

    get deviationsAccepted() {
        return Object.values(this.deviations).every(Boolean);
    },

    // The card field exists only with Stripe keys; without them the test gateway stands in and asks for nothing.
    get cardMissing() {
        return this.payment === 'card' && Boolean(stripeKey) && !this.cardComplete;
    },

    get ready() {
        return this.accepted && this.deviationsAccepted && (this.payment !== 'blik' || this.blik.length === 6) && !this.cardMissing;
    },

    // What the pay button says: the amount, or what is still missing.
    get label() {
        if (this.submitting) {
            return say(texts, 'paying');
        }

        if (this.payment === 'blik' && this.blik.length !== 6) {
            return say(texts, 'blik_missing');
        }

        if (this.cardMissing) {
            return say(texts, 'card_missing');
        }

        if (!this.deviationsAccepted) {
            return say(texts, 'deviation_missing');
        }

        return this.accepted ? say(texts, 'pay', this.$store.cart.format(this.total)) : say(texts, 'terms_missing');
    },

    init() {
        this.$el.querySelector('[aria-invalid="true"]')?.focus();

        if (stripeKey && this.$refs.card) {
            this.mountCard();
        }
    },

    // Stripe's own field: the card number is typed into Stripe's frame, never into our page.
    mountCard() {
        stripe ??= window.Stripe(stripeKey);
        card = stripe.elements({ locale: document.documentElement.lang || 'auto' }).create('card', { hidePostalCode: true });
        card.mount(this.$refs.card);
        card.on('change', (event) => (this.cardComplete = event.complete));
    },

    submit(event) {
        if (this.submitting) {
            event.preventDefault();

            return;
        }

        if (this.payment === 'blik' && this.blik.length !== 6) {
            event.preventDefault();
            this.$store.cart.say(say(texts, 'blik_short'));
            this.$refs.blik.focus();

            return;
        }

        if (this.cardMissing) {
            event.preventDefault();
            this.$store.cart.say(say(texts, 'card_missing'));
            card?.focus();

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

        if (this.confirmsInBrowser) {
            event.preventDefault();
            this.pay(event.target);
        }
    },

    async pay(form) {
        let response;

        try {
            response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
        } catch {
            this.stop(say(texts, 'offline'));

            return;
        }

        // The cart or a price changed meanwhile: the server sends us back to the corrected screen.
        if (response.redirected) {
            window.location = response.url;

            return;
        }

        // Something in the form is wrong — let the server draw the page with the errors, as without JavaScript.
        if (!response.ok) {
            form.submit();

            return;
        }

        const { secret, next } = await response.json();
        stripe ??= window.Stripe(stripeKey);
        const billing = { name: form.elements.name.value, email: form.elements.email.value };

        const confirm = {
            blik: () => stripe.confirmBlikPayment(secret, {
                payment_method: { blik: {}, billing_details: billing },
                payment_method_options: { blik: { code: this.blik } },
            }),
            // A card may ask for the bank's own check (3-D Secure) in a window Stripe opens over the page.
            card: () => stripe.confirmCardPayment(secret, {
                payment_method: { card, billing_details: billing },
            }),
            // Przelewy24 takes the customer to their bank and brings them back to the confirmation.
            online_transfer: () => stripe.confirmP24Payment(secret, {
                payment_method: { p24: {}, billing_details: billing },
                return_url: next,
            }),
        }[this.payment];

        const { error } = await confirm();

        if (error) {
            this.stop(error.message || say(texts, 'declined'));

            return;
        }

        window.location = next;
    },

    // The order is saved and waiting; the cart stays full, so a second try costs nothing.
    stop(message) {
        this.submitting = false;
        this.$store.cart.say(message);
    },
});
