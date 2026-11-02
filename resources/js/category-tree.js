// The tree of shelves in the panel (MA-351). A shelf moves by its handle, with a mouse or a finger: within its list,
// to the other branch, or into another shelf to become its subcategory. Every shelf has a list under it for that, empty
// or not; while a shelf is lifted, the lists it still fits in (three levels at most, never inside itself) open as drop
// zones. ↑ and ↓ on the focused handle move it within its list. The whole tree is saved at once after every move.
const send = (url, body, token) => fetch(url, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
    body: JSON.stringify(body),
});

export default ({ arrangeUrl, token, maxDepth = 3 }) => ({
    notice: '',
    lifted: null,

    init() {
        // SortableJS loads only on this screen, as on the product list.
        import('sortablejs').then(({ default: Sortable }) => {
            this.$root.querySelectorAll('[data-children-of]').forEach((list) => Sortable.create(list, {
                group: 'categories',
                handle: '[data-drag]',
                draggable: '[data-id]',
                animation: 180,
                // The same drag with a mouse and on a phone; the page scrolls when the shelf reaches the edge.
                forceFallback: true,
                supportPointer: true,
                fallbackTolerance: 4,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                emptyInsertThreshold: 14,
                scroll: true,
                bubbleScroll: true,
                scrollSensitivity: 90,
                ghostClass: 'drag-ghost',
                dragClass: 'drag-lifted',
                onStart: (event) => this.lift(event.item),
                onMove: (event) => this.fits(event.dragged, event.to),
                onEnd: (event) => {
                    this.drop();

                    if (event.from !== event.to || event.oldIndex !== event.newIndex) {
                        this.save(event.item);
                    }
                },
            }));
        });
    },

    // Glina's own list is level 2; each list inside a shelf one deeper.
    depthOf(list) {
        let depth = 2;

        for (let step = list.parentElement?.closest('[data-children-of]'); step; step = step.parentElement?.closest('[data-children-of]')) {
            depth++;
        }

        return depth;
    },

    // How many levels the shelf takes with everything under it: 1 when nothing is under it.
    heightOf(item) {
        const children = [...item.querySelector(':scope > [data-children-of]')?.children ?? []];

        return 1 + Math.max(0, ...children.map((child) => this.heightOf(child)));
    },

    fits(item, list) {
        return ! item.contains(list) && this.depthOf(list) + this.heightOf(item) - 1 <= maxDepth;
    },

    lift(item) {
        this.lifted = item;
        this.$root.querySelectorAll('[data-children-of]').forEach((list) => list.toggleAttribute('data-accepts', this.fits(item, list)));
        this.$root.classList.add('is-dragging');
    },

    drop() {
        this.lifted = null;
        this.$root.classList.remove('is-dragging');
        this.$root.querySelectorAll('[data-accepts]').forEach((list) => list.removeAttribute('data-accepts'));
    },

    // ↑ and ↓ on the handle, for a keyboard: the shelf moves one place within its list, saved once the keys stop.
    step(handle, by) {
        const item = handle.closest('[data-id]');
        const neighbour = by < 0 ? item.previousElementSibling : item.nextElementSibling;

        if (! neighbour) {
            return;
        }

        if (by < 0) {
            neighbour.before(item);
        } else {
            neighbour.after(item);
        }

        handle.focus();
        clearTimeout(this.waiting);
        this.waiting = setTimeout(() => this.save(item), 600);
    },

    // Every shelf with its parent, in the order on the screen.
    tree() {
        return [...this.$root.querySelectorAll('[data-children-of]')].flatMap((list) => [...list.children]
            .filter((item) => item.matches('[data-id]'))
            .map((item) => ({ id: Number(item.dataset.id), parent_id: Number(list.dataset.childrenOf) })));
    },

    async save(item) {
        const name = item.querySelector('[data-name]')?.textContent.trim();
        const parent = item.parentElement.closest('[data-id]')?.querySelector('[data-name]')?.textContent.trim()
            ?? item.closest('section')?.querySelector('h2')?.textContent.trim();

        try {
            const response = await send(arrangeUrl, { tree: this.tree() }, token);
            const { message } = await response.json();

            this.say(response.ok ? `„${name}” stoi teraz w „${parent}”. Zapisane — tak stoi w sklepie.` : message);

            // The tree on the screen no longer matches the saved one: back to what is saved.
            if (! response.ok) {
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
        }, 4000);
    },

    // „+ Półka” in the tree: the form at the top, with this shelf chosen as the place.
    addUnder(id) {
        const form = document.getElementById('nowa-polka');

        form.elements.parent_id.value = String(id);
        form.scrollIntoView({ block: 'start', behavior: 'smooth' });
        form.elements.name.focus({ preventScroll: true });
    },
});
