/*
 * The price and „Do koszyka” pinned to the bottom of a phone screen on a product page and on the mug configurator,
 * so they never scroll away while the description is read. The bar steps aside while any part of the form is in view
 * (`data-buy-zone`: the size picker and the form), so it never covers the sizes or doubles the form's own button.
 */
export default (zone = '[data-buy-zone]') => ({
    shown: false,

    init() {
        const parts = document.querySelectorAll(zone);

        if (!parts.length) {
            return;
        }

        const visible = new Set();

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => (entry.isIntersecting ? visible.add(entry.target) : visible.delete(entry.target)));
            this.shown = visible.size === 0;
        });

        parts.forEach((part) => observer.observe(part));
    },
});
