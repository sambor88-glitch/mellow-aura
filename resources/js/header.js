/*
 * The header sticks to the top of the screen. On a phone the menu wraps into rows above the cart and the header
 * grew to a quarter of the screen, so there the rows above the cart slide away with the page and only the cart
 * row stays. Where everything fits in one row nothing moves; without JavaScript the whole header sticks.
 * The page's scroll padding follows the header, so keyboard focus is never hidden under it (WCAG 2.4.11).
 */
const GAP_ABOVE_CART = 10;

export default () => ({
    offset: 0,

    init() {
        const measure = () => {
            const { logo, actions } = this.$refs;
            const ownRow = actions.offsetTop >= logo.offsetTop + logo.offsetHeight;

            this.offset = ownRow ? actions.offsetTop - GAP_ABOVE_CART : 0;
            // Whatever the browser scrolls to — a focused link, #formularz — lands below the part of the header that stays.
            document.documentElement.style.scrollPaddingTop = this.$el.offsetHeight - this.offset + 12 + 'px';
        };

        measure();
        new ResizeObserver(measure).observe(this.$el);
    },
});
