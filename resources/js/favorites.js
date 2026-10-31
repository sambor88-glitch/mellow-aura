import { text } from './cart';

/*
 * Favourites, saved with the heart on a product. As the privacy policy says, the list lives only in this
 * browser's storage and never reaches the server: the shop page filters its own cards with it.
 */
const KEY = 'mellowaura-favorites';

export default {
    ids: [],

    init() {
        this.load();
        // Another tab saved or removed a favourite.
        window.addEventListener('storage', (event) => event.key === KEY && this.load());
    },

    load() {
        try {
            const stored = JSON.parse(window.localStorage.getItem(KEY) ?? '[]');
            this.ids = Array.isArray(stored) ? stored.filter(Number.isInteger) : [];
        } catch {
            this.ids = [];
        }
    },

    get count() {
        return this.ids.length;
    },

    has(id) {
        return this.ids.includes(id);
    },

    toggle(id) {
        const saved = !this.has(id);
        this.ids = saved ? [...this.ids, id] : this.ids.filter((other) => other !== id);

        try {
            window.localStorage.setItem(KEY, JSON.stringify(this.ids));
        } catch {
            // Blocked storage: the heart still works until the page is left.
        }

        // Below 520 px the header has no heart (header.blade.php), so the shop filter is the way back (MA-294).
        const where = window.matchMedia('(min-width: 520px)').matches ? 'favoriteSavedHeader' : 'favoriteSavedShop';
        window.Alpine.store('cart').say(text(saved ? where : 'favoriteRemoved'));
    },
};
