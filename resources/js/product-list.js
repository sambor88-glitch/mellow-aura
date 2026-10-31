// The product list in the panel (MA-307). Search and filters work on the rows already on the page and come back
// as they were after a visit to a product card. Without a script the list simply shows every product.
const KEY = 'panel.products.list';

// „Miska”, „miska” and „MISKA” match; so do „łyżka” and „lyzka”.
const fold = (text) => text.toLowerCase().replace(/ł/g, 'l').normalize('NFD').replace(/[̀-ͯ]/g, '');

export default (ordering = false) => ({
    q: '',
    filter: 'all',
    ordering,

    init() {
        try {
            const saved = JSON.parse(sessionStorage.getItem(KEY) ?? '{}');
            this.q = typeof saved.q === 'string' ? saved.q : '';
            this.filter = typeof saved.filter === 'string' ? saved.filter : 'all';
        } catch {
            // Private mode or blocked storage: the list starts unfiltered.
        }

        this.$watch('q', () => this.remember());
        this.$watch('filter', () => this.remember());
    },

    remember() {
        try {
            sessionStorage.setItem(KEY, JSON.stringify({ q: this.q, filter: this.filter }));
        } catch {
            // Nothing to keep it in; the filter still works on this page.
        }
    },

    shows(row) {
        const terms = fold(this.q).split(/\s+/).filter(Boolean);

        return (this.ordering || this.filter === 'all' || row.dataset.states.split(' ').includes(this.filter))
            && (this.ordering || terms.every((term) => row.dataset.search.includes(term)));
    },

    get empty() {
        return ! [...this.$root.querySelectorAll('[data-states]')].some((row) => this.shows(row));
    },
});
