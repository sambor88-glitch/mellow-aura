// Text areas in the panel grow with their text, so a paragraph is never cut in the middle of a line (MA-335).
// The rows attribute stays the smallest height; above 60% of the screen the field scrolls inside.
// A field in a closed tab or a folded list has no height yet: it is measured when it comes into view.
function fit(area) {
    if (!area.isConnected || area.offsetParent === null) {
        return;
    }
    if (!area.dataset.minHeight) {
        area.dataset.minHeight = String(area.offsetHeight);
    }
    const style = getComputedStyle(area);
    const border = parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
    const max = Math.round(innerHeight * 0.6);
    area.style.height = 'auto';
    const wanted = Math.max(Number(area.dataset.minHeight), area.scrollHeight + border);
    area.style.height = `${Math.min(wanted, max)}px`;
    area.style.overflowY = wanted > max ? 'auto' : 'hidden';
}

export default function autosize(root = document) {
    const areas = [...root.querySelectorAll('[data-autosize] textarea:not([data-fixed-height])')];
    if (areas.length === 0) {
        return;
    }
    const seen = new IntersectionObserver((entries) => entries.forEach((entry) => entry.isIntersecting && fit(entry.target)));
    for (const area of areas) {
        area.style.resize = 'none';
        area.addEventListener('input', () => fit(area));
        seen.observe(area);
    }
    // Tabs and folded lists show fields without scrolling them into view.
    document.addEventListener('toggle', (event) => event.target.querySelectorAll?.('textarea').forEach(fit), true);
    document.addEventListener('click', (event) => {
        if (event.target.closest?.('[role="tab"]')) {
            requestAnimationFrame(() => areas.forEach(fit));
        }
    });
    addEventListener('resize', () => areas.forEach(fit), { passive: true });
}
