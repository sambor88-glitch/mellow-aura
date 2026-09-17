/*
 * The price and „Do koszyka” pinned to the bottom of a phone screen on a product page, so they never scroll away
 * while the description is read. The bar steps aside while the form's own button is in view.
 */
export default () => ({
    shown: false,

    init() {
        const button = document.getElementById('add-to-cart')?.querySelector('[type="submit"]');

        if (!button) {
            return;
        }

        new IntersectionObserver(([entry]) => (this.shown = !entry.isIntersecting)).observe(button);
    },
});
