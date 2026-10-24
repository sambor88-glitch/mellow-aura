import Alpine from 'alpinejs';

/*
 * Product card: on hover the gallery crossfades every 1.1 s (mellowaura-design, "Ruch").
 * On touch screens nothing cycles — the cover and the dots stay.
 */
Alpine.data('cardGallery', (count) => ({
    index: 0,
    timer: null,
    start() {
        if (count < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        this.stop();
        this.timer = setInterval(() => {
            this.index = (this.index + 1) % count;
        }, 1100);
    },
    stop() {
        clearInterval(this.timer);
        this.timer = null;
        this.index = 0;
    },
}));

/*
 * Product page: gallery, zoom, chosen variant and quantity.
 * Variants come from the server as JSON; the page works without JS too —
 * the first available variant is checked and its price is rendered.
 */
Alpine.data('productPage', ({ photos, variants, selected }) => ({
    photos,
    variants,
    photo: 0,
    zoomed: false,
    variantId: selected,
    qty: 1,
    get variant() {
        return this.variants.find((variant) => variant.id === this.variantId) ?? this.variants[0];
    },
    get maxQty() {
        return Math.max(1, this.variant.stock);
    },
    get total() {
        return this.variant.price * this.qty;
    },
    choose(id) {
        this.variantId = id;
        this.qty = Math.min(this.qty, this.maxQty);
    },
    inc() {
        this.qty = Math.min(this.qty + 1, this.maxQty);
    },
    dec() {
        this.qty = Math.max(1, this.qty - 1);
    },
    openZoom() {
        this.zoomed = true;
        this.$nextTick(() => this.$refs.zoomClose?.focus());
    },
    closeZoom() {
        this.zoomed = false;
        this.$nextTick(() => this.$refs.photoButton?.focus());
    },
    money(grosze) {
        const zloty = Math.floor(grosze / 100);
        const rest = String(grosze % 100).padStart(2, '0');

        return `${zloty},${rest} zł`;
    },
}));

window.Alpine = Alpine;
Alpine.start();
