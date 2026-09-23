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

        window.Alpine.store('cart').say(saved ? 'Zapisane w ulubionych — znajdziesz je w nagłówku' : 'Usunięte z ulubionych');
    },
};
