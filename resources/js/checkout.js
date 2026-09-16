/*
 * The one-screen checkout keeps the delivery cost and the pay button in step with the customer's
 * choices. The server counts everything again; these numbers are only what the screen shows.
 */
export default ({ accepted, payment, shipping, subtotal, freeFrom, prices }) => ({
    accepted,
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

    get ready() {
        return this.accepted && (this.payment !== 'blik' || this.blik.length === 6);
    },

    // What the pay button says: the amount, or what is still missing.
    get label() {
        if (this.submitting) {
            return 'Płacę…';
        }

        if (this.payment === 'blik' && this.blik.length !== 6) {
            return 'Wpisz kod BLIK, żeby zapłacić';
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

        if (!this.accepted) {
            event.preventDefault();
            this.$store.cart.say('Zaznacz akceptację regulaminu, żeby zapłacić');
            this.$refs.terms.focus();

            return;
        }

        this.submitting = true;
    },
});
