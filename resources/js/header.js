/*
 * The header is a glass pill that sticks to the top of the screen (mellowaura-design, „Nagłówek-pigułka”).
 * The page's scroll padding follows its height, so keyboard focus is never hidden under it (WCAG 2.4.11).
 * Sliding it away while scrolling down is motion, so resources/js/aura.js does that.
 */
export default () => ({
    init() {
        const measure = () => {
            document.documentElement.style.scrollPaddingTop = this.$el.offsetHeight + 12 + 'px';
        };

        measure();
        new ResizeObserver(measure).observe(this.$el);
    },
});
