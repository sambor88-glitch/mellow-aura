// Tabs on long panel screens (MA-315, <x-admin::tabs>). The server opens the tab from the address or the first one;
// here the screen moves to a better one: a tab with a mistake after saving, the tab a #link points into, or the tab used
// last on this screen, so a save that comes back to the screen lands where Kasia was. The address keeps the open tab.
const MISTAKE = '[aria-invalid="true"], [data-error]';

export default (initial, keys) => ({
    tab: initial,
    // Tabs with a mistake in them, for the red dot.
    bad: [],

    init() {
        // A tab may show more than one part: one beside a form it cannot sit in (the mail image, MA-324).
        const parts = (key) => [...this.$root.querySelectorAll(`[data-tab-panel="${CSS.escape(key)}"]`)];
        const holding = (element) => (element ? keys.find((key) => parts(key).some((part) => part.contains(element))) : undefined);

        this.bad = keys.filter((key) => parts(key).some((part) => part.querySelector(MISTAKE)));

        const asked = new URL(location.href).searchParams.get('tab');
        const target = this.hashed();
        let last = null;
        try {
            last = sessionStorage.getItem(this.memory());
        } catch {
            // Without storage the screen opens on the tab from the server.
        }

        // A #link is more exact than ?tab=: it names the field or the card.
        const next = this.bad[0]
            ?? holding(target)
            ?? (keys.includes(asked) ? asked : undefined)
            ?? (keys.includes(last) ? last : undefined)
            ?? initial;

        if (next !== this.tab) {
            this.show(next);
        }
        if (target && holding(target) === next) {
            this.reach(target);
        }

        // A #link followed on the same screen (the search, a „Dodaj →” link) opens its tab too.
        window.addEventListener('hashchange', () => {
            const element = this.hashed();
            const key = holding(element);
            if (key) {
                this.show(key);
                this.reach(element);
            }
        });

        // Something on the screen wants to show a field in another tab, like „Wróć do …” of unsaved changes (MA-251).
        this.$root.addEventListener('reveal', (event) => {
            const key = holding(event.target);
            if (key && key !== this.tab) {
                this.show(key);
            }
        });
    },

    // The element the address points at with #id, if it is on this screen.
    hashed() {
        try {
            return location.hash.length > 1 ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
        } catch {
            // A broken #link only means no tab is chosen by it.
            return null;
        }
    },

    // A #link to a folded part opens it too (a gift set, a mail); a link to a field puts the cursor in it.
    reach(target) {
        target.closest('details')?.setAttribute('open', '');
        this.$nextTick(() => {
            target.scrollIntoView({ block: 'start' });
            if (target.matches('input, select, textarea')) {
                target.focus({ preventScroll: true });
            }
        });
    },

    memory() {
        return `panel-tab:${location.pathname}`;
    },

    show(tab) {
        this.tab = tab;

        try {
            const url = new URL(location.href);
            url.searchParams.set('tab', tab);
            history.replaceState(history.state, '', url);
        } catch {
            // The tab still opens; only the address stays as it was.
        }
        try {
            sessionStorage.setItem(this.memory(), tab);
        } catch {
            // Remembering the tab is a convenience.
        }
    },

    // Arrow keys, Home and End move between tabs, as screen readers expect of a tab list.
    move(event) {
        const tabs = [...this.$refs.tablist.querySelectorAll('[role=tab]')];
        const at = tabs.indexOf(event.target);
        const to = { ArrowRight: at + 1, ArrowLeft: at - 1, Home: 0, End: tabs.length - 1 }[event.key];

        if (at < 0 || to === undefined) {
            return;
        }

        event.preventDefault();
        const next = tabs[(to + tabs.length) % tabs.length];
        this.show(next.dataset.tab);
        next.focus();
    },
});
