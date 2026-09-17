/*
 * The one-screen checkout keeps the delivery cost and the pay button in step with the customer's
 * choices. The server counts everything again; these numbers are only what the screen shows.
 */
export default ({ accepted, deviations, payment, shipping, subtotal, freeFrom, prices, stripeKey }) => ({
    accepted,
    // A product with a feature nobody would expect has its own checkbox, keyed by the cart line.
    deviations: { ...deviations },
    payment,
    shipping,
    blik: '',
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

    get ready() {
        return this.accepted && this.deviationsAccepted && (this.payment !== 'blik' || this.blik.length === 6);
    },

    // What the pay button says: the amount, or what is still missing.
    get label() {
        if (this.submitting) {
            return 'Płacę…';
        }

        if (this.payment === 'blik' && this.blik.length !== 6) {
            return 'Wpisz kod BLIK, żeby zapłacić';
        }

        if (!this.deviationsAccepted) {
            return 'Zaznacz „Akceptuję” przy produkcie';
        }

        return this.accepted ? 'Płacę ' + this.$store.cart.format(this.total) : 'Zaakceptuj regulamin, żeby zapłacić';
    },

    init() {
        this.$el.querySelector('[aria-invalid="true"]')?.focus();
    },

    submit(event) {
        if (this.submitting) {
            event.preventDefault();

            return;
        }

        if (this.payment === 'blik' && this.blik.length !== 6) {
            event.preventDefault();
            this.$store.cart.say('Wpisz 6-cyfrowy kod z aplikacji banku');
            this.$refs.blik.focus();

            return;
        }

        if (!this.deviationsAccepted) {
            event.preventDefault();
            this.$store.cart.say('Zaznacz „Akceptuję” przy produkcie, żeby zapłacić');
            this.$el.querySelector('[data-deviation]:not(:checked)')?.focus();

            return;
        }

        if (!this.accepted) {
            event.preventDefault();
            this.$store.cart.say('Zaznacz akceptację regulaminu, żeby zapłacić');
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
            this.stop('Brak połączenia. Sprawdź internet i spróbuj jeszcze raz');

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
        const stripe = window.Stripe(stripeKey);
        const billing = { name: form.elements.name.value, email: form.elements.email.value };

        const { error } = this.payment === 'blik'
            ? await stripe.confirmBlikPayment(secret, {
                payment_method: { blik: {}, billing_details: billing },
                payment_method_options: { blik: { code: this.blik } },
            })
            // Przelewy24 takes the customer to their bank and brings them back to the confirmation.
            : await stripe.confirmP24Payment(secret, {
                payment_method: { p24: {}, billing_details: billing },
                return_url: next,
            });

        if (error) {
            this.stop(error.message || 'Bank nie potwierdził płatności. Spróbuj jeszcze raz albo zapłać przelewem');

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
