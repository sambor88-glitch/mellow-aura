/*
 * The one-screen checkout keeps the delivery cost and the pay button in step with the customer's
 * choices. The server counts everything again; these numbers are only what the screen shows.
 */
export default ({ payment, shipping, subtotal, freeFrom, prices }) => ({
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
        return this.payment !== 'blik' || this.blik.length === 6;
    },

    init() {
        this.$el.querySelector('[aria-invalid="true"]')?.focus();
    },

    submit(event) {
        if (this.submitting) {
            event.preventDefault();

            return;
        }

        if (!this.ready) {
            event.preventDefault();
            this.$store.cart.say('Wpisz 6-cyfrowy kod z aplikacji banku');
            this.$refs.blik.focus();

            return;
        }

        this.submitting = true;
    },
});
