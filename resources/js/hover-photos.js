/*
 * The second photo of a product card spills over the first on hover. It has no src until a mouse comes over the card
 * or the keyboard reaches it: loaded with the page, it shared a slow line with the first photo, and a phone never
 * shows it (MA-271).
 */
export default function hoverPhotos() {
    const load = (card) => {
        card?.querySelectorAll('img[data-hover-src]').forEach((img) => {
            if (img.dataset.hoverSrcset) {
                img.srcset = img.dataset.hoverSrcset;
            }
            img.src = img.dataset.hoverSrc;
            img.removeAttribute('data-hover-src');
        });
    };

    document.addEventListener('pointerover', (event) => {
        if (event.pointerType === 'mouse') {
            load(event.target.closest?.('[data-shelf-card]'));
        }
    });
    document.addEventListener('focusin', (event) => load(event.target.closest?.('[data-shelf-card]')));
}
