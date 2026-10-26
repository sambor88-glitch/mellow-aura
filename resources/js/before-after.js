/*
 * A „przed i po” photo pair: the before photo is cut at the handle. Drag anywhere on the photo, or use the
 * arrow keys on the handle (Shift moves further, Home and End go to the edges). Without JavaScript the pair
 * stays split in half.
 */
export default () => ({
    pos: 50,
    dragging: false,

    start(event) {
        this.dragging = true;
        event.currentTarget.setPointerCapture?.(event.pointerId);
        this.drag(event);
    },

    drag(event) {
        if (!this.dragging) {
            return;
        }

        const box = event.currentTarget.getBoundingClientRect();
        this.pos = Math.round(Math.min(100, Math.max(0, ((event.clientX - box.left) / box.width) * 100)));
    },

    stop() {
        this.dragging = false;
    },

    key(event) {
        const step = event.shiftKey ? 20 : 5;
        const moves = { ArrowLeft: -step, ArrowDown: -step, ArrowRight: step, ArrowUp: step, Home: -100, End: 100 };

        if (moves[event.key] === undefined) {
            return;
        }

        event.preventDefault();
        this.pos = Math.min(100, Math.max(0, this.pos + moves[event.key]));
    },
});
