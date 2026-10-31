// The product card in the panel (MA-307): one form in tabs. The tabs are the shared panel tabs (MA-315, panel-tabs.js),
// so every tab stays in the form and saving sends all of them.
export default (promo = false) => ({
    // „Promocja” shows the price before a reduction in both currencies; switched off, those fields are not sent.
    promo,

    // − and + next to the pieces on the shelf. The input event tells the unsaved-changes guard (MA-251).
    step(button, by) {
        const input = button.parentElement.querySelector('input');
        input.value = Math.max(0, (parseInt(input.value, 10) || 0) + by);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    },
});
