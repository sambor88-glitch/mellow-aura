// The search at the top of every panel screen (MA-308): Ctrl+K or Cmd+K, „/”, or the magnifier on a phone. Results come
// from /panel/szukaj in groups. Before anything is typed it shows what was opened last on this device and the quick
// actions. The field is a combobox and the results a listbox: ↑ ↓ choose, Enter opens, Esc closes and gives the focus
// back. A result is a link, so opening it goes through the question about unsaved changes like any other link (MA-251).
const RECENT = 5;
const WAIT = 160;

// One letter for one letter: lower case, no Polish marks, ł as l. The same as PanelSearch::fold() on the server.
const foldChar = (char) => {
    const folded = char.toLowerCase().replace('ł', 'l').normalize('NFD').replace(/[̀-ͯ]/g, '');

    return folded.length === 1 ? folded : (char.length === 1 ? char : '�');
};
const fold = (text) => Array.from(String(text ?? '')).map(foldChar).join('');
const words = (text) => fold(text).trim().split(/\s+/).filter(Boolean);

// Typing in a field keeps „/” as a letter.
const typing = (element) => element instanceof HTMLElement
    && (element.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName));

export default (config) => ({
    open: false,
    term: '',
    groups: [],
    active: 0,
    loading: false,
    failed: false,
    status: '',
    shortcut: /Mac|iPhone|iPad/.test(navigator.userAgent) ? '⌘ K' : 'Ctrl K',
    trigger: null,
    timer: null,
    request: null,
    // Whether the results on screen answer what is typed, not the start list.
    searched: false,
    // Enter pressed before the answer came: open the first result as soon as it does.
    waiting: false,

    init() {
        window.addEventListener('keydown', (event) => {
            if (event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey) && ! event.altKey && ! event.shiftKey) {
                event.preventDefault();
                this.open ? this.hide() : this.show();
            } else if (event.key === '/' && ! this.open && ! event.metaKey && ! event.ctrlKey && ! typing(event.target)) {
                event.preventDefault();
                this.show();
            }
        });

        // A setting found by its name opens with the cursor in its field. Screens with tabs do it in panel-tabs.js.
        const reach = () => {
            const field = this.hashed();
            if (field?.matches('input, select, textarea') && field.offsetParent !== null) {
                field.focus({ preventScroll: true });
            }
        };
        requestAnimationFrame(reach);
        window.addEventListener('hashchange', () => requestAnimationFrame(reach));

        this.$watch('term', () => this.ask());
    },

    hashed() {
        try {
            return location.hash.length > 1 ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
        } catch {
            return null;
        }
    },

    show() {
        if (this.open) {
            return;
        }
        this.trigger = document.activeElement;
        // On a phone the menu and the search take the same screen.
        if (this.menu) {
            this.menu = false;
        }
        this.open = true;
        this.term = '';
        this.start();
        // At once, still within the tap: a phone opens its keyboard only for a field focused by the tap itself.
        this.$refs.overlay.style.removeProperty('display');
        this.$refs.input.focus({ preventScroll: true });
    },

    hide(restore = true) {
        this.open = false;
        clearTimeout(this.timer);
        this.request?.abort();
        // After the trap has let go of the focus (x-trap.noreturn: the field was focused before it started).
        const trigger = this.trigger;
        if (restore && trigger instanceof HTMLElement) {
            this.$nextTick(() => trigger.focus({ preventScroll: true }));
        }
    },

    // Nothing typed yet: what was opened last here, and the quick actions.
    start() {
        const recent = this.recent();
        this.fill([
            ...(recent.length ? [{ label: 'Ostatnio otwierane', results: recent }] : []),
            ...(config.quick.length ? [{ label: 'Szybko', results: config.quick }] : []),
        ]);
        this.failed = false;
        this.loading = false;
        this.searched = false;
        this.waiting = false;
        this.status = '';
    },

    typed() {
        return fold(this.term).trim().length >= 2;
    },

    ask() {
        clearTimeout(this.timer);
        this.request?.abort();

        if (! this.typed()) {
            this.start();

            return;
        }

        // The start list goes away, so Enter never opens a quick action meant for no query.
        if (! this.searched) {
            this.fill([]);
        }
        this.loading = true;
        this.timer = setTimeout(() => this.fetch(this.term), WAIT);
    },

    async fetch(term) {
        const request = new AbortController();
        this.request = request;

        try {
            const response = await fetch(`${config.url}?q=${encodeURIComponent(term.trim())}`, {
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });
            if (! response.ok) {
                throw new Error(String(response.status));
            }
            const { groups } = await response.json();
            if (term !== this.term) {
                return;
            }
            this.fill(groups);
            this.searched = true;
            this.failed = false;
            const count = this.items().length;
            this.status = count === 0
                ? `Nic nie pasuje do „${term.trim()}”.`
                : `${count} ${count === 1 ? 'wynik' : (count % 10 >= 2 && count % 10 <= 4 && (count % 100 < 10 || count % 100 >= 20) ? 'wyniki' : 'wyników')}. Strzałki wybierają, Enter otwiera.`;
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            this.fill([]);
            this.failed = true;
            this.status = 'Wyszukiwarka nie odpowiada. Spróbuj jeszcze raz za chwilę.';
        } finally {
            if (this.request === request) {
                this.loading = false;
                if (this.waiting) {
                    this.waiting = false;
                    this.$nextTick(() => this.pick());
                }
            }
        }
    },

    // Every result gets its place in the whole list, for the arrows and aria-activedescendant.
    fill(groups) {
        let index = 0;
        this.groups = groups.map((group) => ({
            ...group,
            results: group.results.map((result) => ({ ...result, index: index++ })),
        }));
        this.active = 0;
    },

    items() {
        return this.groups.flatMap((group) => group.results);
    },

    optionId(index) {
        return `szukaj-wynik-${index}`;
    },

    activeId() {
        return this.items().length ? this.optionId(this.active) : null;
    },

    move(step) {
        const count = this.items().length;
        if (! count) {
            return;
        }
        this.active = (this.active + step + count) % count;
        this.$nextTick(() => document.getElementById(this.optionId(this.active))?.scrollIntoView({ block: 'nearest' }));
    },

    // Enter clicks the chosen link, so it goes the way a tap would.
    pick() {
        if (this.loading) {
            this.waiting = true;

            return;
        }
        document.getElementById(this.optionId(this.active))?.click();
    },

    chosen(result) {
        this.remember(result);
        this.hide(false);
    },

    // The words of the query in a title, for the bold letters.
    parts(text) {
        const chars = Array.from(String(text ?? ''));
        const folded = chars.map(foldChar).join('');
        const hit = chars.map(() => false);

        for (const word of words(this.term)) {
            if (word.length < 2) {
                continue;
            }
            for (let at = folded.indexOf(word); at >= 0; at = folded.indexOf(word, at + word.length)) {
                hit.fill(true, at, at + word.length);
            }
        }

        const parts = [];
        chars.forEach((char, index) => {
            const last = parts[parts.length - 1];
            if (last && last.hit === hit[index]) {
                last.text += char;
            } else {
                parts.push({ text: char, hit: hit[index] });
            }
        });

        return parts;
    },

    // What was opened last stays on this device only, for this account.
    memory() {
        return `panel-search:${config.account}`;
    },

    recent() {
        try {
            const saved = JSON.parse(localStorage.getItem(this.memory()) ?? '[]');

            return Array.isArray(saved) ? saved.filter((result) => typeof result?.url === 'string' && typeof result?.title === 'string').slice(0, RECENT) : [];
        } catch {
            return [];
        }
    },

    remember(result) {
        const { title, url, detail = null, image = null, icon = null } = result;
        try {
            const kept = this.recent().filter((saved) => saved.url !== url);
            localStorage.setItem(this.memory(), JSON.stringify([{ title, url, detail, image, icon }, ...kept].slice(0, RECENT)));
        } catch {
            // Without storage the search simply starts without „Ostatnio otwierane”.
        }
    },
});
