/*
 * The price and „Do koszyka” pinned to the bottom of a phone screen on a product page and on the mug configurator,
 * so they never scroll away while the description is read. The bar steps aside while the form's own button is in view.
 */
export default (formButton = '#add-to-cart [type="submit"]') => ({
    shown: false,

    init() {
        const button = document.querySelector(formButton);

        if (!button) {
            return;
        }

        new IntersectionObserver(([entry]) => (this.shown = !entry.isIntersecting)).observe(button);
    },
});
