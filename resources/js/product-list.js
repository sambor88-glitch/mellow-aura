// The product list in the panel (MA-307). Search and filters work on the rows already on the page and come back
// as they were after a visit to a product card. Rows move by their handle: grabbed and dropped anywhere, with a mouse
// or a finger, or with ↑ and ↓ on the focused handle. The new order is saved at once. Without a script the list shows
// every product.
const KEY = 'panel.products.list';

// „Miska”, „miska” and „MISKA” match; so do „łyżka” and „lyzka”.
const fold = (text) => text.toLowerCase().replace(/ł/g, 'l').normalize('NFD').replace(/[̀-ͯ]/g, '');

export default ({ reorderUrl, token }) => ({
    q: '',
    filter: 'all',
    notice: '',

    init() {
        try {
            const saved = JSON.parse(sessionStorage.getItem(KEY) ?? '{}');
            this.q = typeof saved.q === 'string' ? saved.q : '';
            this.filter = typeof saved.filter === 'string' ? saved.filter : 'all';
        } catch {
            // Private mode or blocked storage: the list starts unfiltered.
        }

        const changed = () => {
            this.remember();
            this.sorter?.option('disabled', ! this.sortable);
        };
        this.$watch('q', changed);
        this.$watch('filter', changed);

        // SortableJS loads only on this screen.
        import('sortablejs').then(({ default: Sortable }) => {
            this.sorter = Sortable.create(this.$refs.rows, {
                handle: '[data-drag]',
                draggable: '[data-id]',
                disabled: ! this.sortable,
                animation: 180,
                // The same drag with a mouse and on a phone; the page scrolls when the row reaches the edge.
                // Pointer events on every browser: Safari on an iPhone has them since iOS 13, and the older touch path did not pick the row up.
                forceFallback: true,
                supportPointer: true,
                fallbackTolerance: 4,
                scroll: true,
                bubbleScroll: true,
                scrollSensitivity: 90,
                ghostClass: 'drag-ghost',
                dragClass: 'drag-lifted',
                onEnd: (event) => {
                    if (event.oldIndex !== event.newIndex) {
                        this.save(event.item);
                    }
                },
            });
        });
    },

    // One order for the whole list: in a filtered or searched list it would not be clear where a row lands.
    get sortable() {
        return this.filter === 'all' && this.q.trim() === '';
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

        return (this.filter === 'all' || row.dataset.states.split(' ').includes(this.filter))
            && terms.every((term) => row.dataset.search.includes(term));
    },

    get empty() {
        return ! [...this.$root.querySelectorAll('[data-states]')].some((row) => this.shows(row));
    },

    // ↑ and ↓ on the handle, for a keyboard: the row moves one place and the order is saved once the keys stop.
    step(handle, by) {
        const row = handle.closest('[data-id]');
        const rows = [...this.$refs.rows.querySelectorAll('[data-id]')];
        const neighbour = rows[rows.indexOf(row) + by];

        if (! neighbour) {
            return;
        }

        if (by < 0) {
            neighbour.before(row);
        } else {
            neighbour.after(row);
        }

        handle.focus();
        clearTimeout(this.waiting);
        this.waiting = setTimeout(() => this.save(row), 600);
    },

    async save(row) {
        const ids = [...this.$refs.rows.querySelectorAll('[data-id]')].map((item) => Number(item.dataset.id));
        const name = row.querySelector('.row-link')?.textContent.trim();

        try {
            const response = await fetch(reorderUrl, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ order: ids }),
            });
            const { message } = await response.json();

            this.say(response.ok ? `„${name}” na ${ids.indexOf(Number(row.dataset.id)) + 1}. miejscu. Zapisane — tak stoi w sklepie.` : message);

            if (response.status === 409) {
                setTimeout(() => location.reload(), 2500);
            }
        } catch {
            this.say('Nie udało się zapisać kolejności — sprawdź internet i przeciągnij jeszcze raz.');
        }
    },

    say(text) {
        this.notice = text;
        clearTimeout(this.hiding);
        this.hiding = setTimeout(() => {
            this.notice = '';
        }, 3500);
    },
});
