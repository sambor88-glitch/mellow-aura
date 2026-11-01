// A row that scrolls sideways (filters, chips) says where more is hidden: data-more="start end" fades that edge in CSS.
// The tab lists of the panel do the same in panel-tabs.js (MA-335).
export default function scrollFade(root = document) {
    for (const row of root.querySelectorAll('[data-scroll-fade]')) {
        const mark = () => {
            const end = row.scrollWidth - row.clientWidth;
            row.dataset.more = end <= 1 ? '' : [row.scrollLeft > 1 ? 'start' : '', row.scrollLeft < end - 1 ? 'end' : ''].join(' ').trim();
        };
        row.addEventListener('scroll', mark, { passive: true });
        addEventListener('resize', mark, { passive: true });
        mark();
    }
}
