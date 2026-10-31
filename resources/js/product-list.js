// The product list in the panel (MA-307). Search and filters work on the rows already on the page and come back
// as they were after a visit to a product card. Rows move by their handle: grabbed and dropped anywhere, with a mouse
// or a finger, or with ↑ and ↓ on the focused handle. The new order is saved at once. The pieces on the shelf change
// in a small window with − and +, and the switch hides a product; both save at once and offer „Cofnij” in the toast.
// Without a script the list shows every product and the switch reloads the page.
const KEY = 'panel.products.list';

// „Miska”, „miska” and „MISKA” match; so do „łyżka” and „lyzka”.
const fold = (text) => text.toLowerCase().replace(/ł/g, 'l').normalize('NFD').replace(/[̀-ͯ]/g, '');

const send = (url, method, body, token) => fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
    body: JSON.stringify(body),
});

export default ({ reorderUrl, stockUrl, toggleUrl, token }) => ({
    q: '',
    filter: 'all',
    notice: '',
    // What „Cofnij” in the toast does, if anything. The view asks `undo !== null`: Alpine calls a function it is handed.
    undo: null,
    // The product whose pieces are open in the − / + window.
    editing: null,
    stockError: '',

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
            const response = await send(reorderUrl, 'PUT', { order: ids }, token);
            const { message } = await response.json();

            this.say(response.ok ? `„${name}” na ${ids.indexOf(Number(row.dataset.id)) + 1}. miejscu. Zapisane — tak stoi w sklepie.` : message);

            if (response.status === 409) {
                setTimeout(() => location.reload(), 2500);
            }
        } catch {
            this.say('Nie udało się zapisać kolejności — sprawdź internet i przeciągnij jeszcze raz.');
        }
    },

    say(text, undo = null) {
        this.notice = text;
        this.undo = undo;
        clearTimeout(this.hiding);
        this.hiding = setTimeout(() => {
            this.notice = '';
            this.undo = null;
        }, undo ? 7000 : 3500);
    },

    runUndo() {
        const undo = this.undo;
        this.undo = null;
        this.notice = '';
        undo?.();
    },

    // The switch: shown or hidden in the shop, without leaving the list.
    async toggle(form, published = null) {
        const row = form.closest('[data-id]');
        const button = form.querySelector('[role=switch]');
        const name = row.querySelector('.row-link')?.textContent.trim();

        try {
            const response = await send(toggleUrl.replace('__id__', row.dataset.id), 'POST', published === null ? {} : { published }, token);
            const data = await response.json();

            if (! response.ok) {
                throw new Error(data.message);
            }

            button.setAttribute('aria-checked', data.published ? 'true' : 'false');
            row.dataset.states = row.dataset.states.split(' ').filter((state) => state !== 'live' && state !== 'hidden')
                .concat(data.published ? 'live' : 'hidden').join(' ');
            this.say(data.message, published === null ? () => this.toggle(form, ! data.published) : null);
        } catch {
            this.say(`Nie udało się zmienić „${name}” — sprawdź internet i spróbuj jeszcze raz.`);
        }
    },

    openStock(button) {
        const row = button.closest('[data-id]');
        const variants = JSON.parse(row.dataset.variants);

        this.stockError = '';
        this.editing = {
            row,
            button,
            id: row.dataset.id,
            name: row.querySelector('.row-link')?.textContent.trim(),
            variants: variants.map((variant) => ({ ...variant, stock: variant.stock ?? '' })),
            before: Object.fromEntries(variants.map((variant) => [variant.id, variant.stock])),
        };
    },

    change(variant, by) {
        variant.stock = Math.max(0, (parseInt(variant.stock, 10) || 0) + by);
        this.queueStock();
    },

    current() {
        return Object.fromEntries(this.editing.variants.map((variant) => [
            variant.id,
            variant.stock === '' || variant.stock === null ? null : Math.max(0, parseInt(variant.stock, 10) || 0),
        ]));
    },

    queueStock() {
        clearTimeout(this.stockTimer);
        const product = this.editing;
        this.stockTimer = setTimeout(() => {
            this.stockTimer = null;
            this.saveStock(product, this.current());
        }, 450);
    },

    async saveStock(product, stock) {
        try {
            const response = await send(stockUrl.replace('__id__', product.id), 'PUT', { stock }, token);
            const data = await response.json();

            if (! response.ok) {
                this.stockError = Object.values(data.errors ?? {})[0]?.[0] ?? data.message;

                return false;
            }

            this.stockError = '';
            product.row.querySelectorAll('[data-stock-label]').forEach((label) => {
                label.textContent = data.stock;
            });
            product.row.dataset.variants = JSON.stringify(JSON.parse(product.row.dataset.variants)
                .map((variant) => ({ ...variant, stock: variant.id in stock ? stock[variant.id] : variant.stock })));

            return true;
        } catch {
            this.stockError = 'Nie udało się zapisać — sprawdź internet i spróbuj jeszcze raz.';

            return false;
        }
    },

    // „Gotowe”, Escape or a tap outside: the last change is sent first, then the toast offers „Cofnij”.
    async closeStock() {
        const product = this.editing;

        if (! product) {
            return;
        }

        const stock = this.current();

        if (this.stockTimer) {
            clearTimeout(this.stockTimer);
            this.stockTimer = null;

            if (! await this.saveStock(product, stock)) {
                return;
            }
        }

        this.editing = null;
        product.button.focus();

        const changed = Object.keys(stock).some((id) => stock[id] !== (product.before[id] ?? null));

        if (changed) {
            this.say(`Sztuki zapisane: „${product.name}”.`, async () => {
                if (await this.saveStock(product, product.before)) {
                    this.say(`Cofnięte: „${product.name}” ma sztuki jak wcześniej.`);
                }
            });
        }
    },
});
