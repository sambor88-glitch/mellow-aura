// The product card in the panel (MA-307): one form in tabs. Every tab stays in the form, so saving sends all of them.
// The address keeps the open tab, so saving, reloading or walking to the next product comes back to it.
export default (initial, promo = false) => ({
    tab: initial,
    // „Promocja” shows the price before a reduction in both currencies; switched off, those fields are not sent.
    promo,

    show(tab) {
        this.tab = tab;

        try {
            const url = new URL(location.href);
            url.searchParams.set('tab', tab);
            history.replaceState(history.state, '', url);
        } catch {
            // The tab still opens; only the address stays as it was.
        }
    },

    // Arrow keys move between tabs, as screen readers expect of a tab list.
    move(event) {
        const tabs = [...this.$root.querySelectorAll('[role=tab]')];
        const next = tabs[(tabs.indexOf(event.target) + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];

        this.show(next.dataset.tab);
        next.focus();
    },

    // − and + next to the pieces on the shelf. The input event tells the unsaved-changes guard (MA-251).
    step(button, by) {
        const input = button.parentElement.querySelector('input');
        input.value = Math.max(0, (parseInt(input.value, 10) || 0) + by);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    },
});
