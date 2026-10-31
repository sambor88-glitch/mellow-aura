/*
 * „Zobacz u siebie” (MA-310): the product's 3D model in a window, and on a phone in augmented reality — the piece
 * stands on the visitor's own table, in its real size. <model-viewer> (Google) does the work: Android opens it in
 * the browser (WebXR) or in Scene Viewer, an iPhone in AR Quick Look with a USDZ copy made on the spot. A computer
 * shows the model to turn around.
 *
 * The library (with three.js) is about a megabyte, so it comes as its own file on the first press, like the 3D mug
 * (MA-232). Without JavaScript, the button is not shown at all.
 *
 * source: the model's address, or a function that makes one (the mug configurator builds its model from the
 * text typed in; MA-310).
 */
export default ({ source, alt, texts }) => {
    // The element stays outside Alpine's reactive state: a proxy around a custom element breaks its own properties.
    let viewer = null;

    return {
        loading: false,
        failed: false,
        // Known only once the model is in: whether this device can put it in the room.
        canAr: false,
        ready: false,

        async open() {
            this.$refs.dialog.showModal();

            if (viewer && typeof source !== 'function' && ! this.failed) {
                return;
            }

            // After a failed load, a fresh viewer: the same address set again would not load again.
            if (this.failed) {
                viewer?.remove();
                viewer = null;
            }

            this.loading = true;
            this.failed = false;
            this.ready = false;

            try {
                const [, src] = await Promise.all([
                    import('@google/model-viewer'),
                    typeof source === 'function' ? source() : source,
                ]);

                if (! viewer) {
                    viewer = this.createViewer();
                }

                viewer.setAttribute('src', src);
            } catch {
                this.loading = false;
                this.failed = true;
            }
        },

        createViewer() {
            const element = document.createElement('model-viewer');
            const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            element.setAttribute('alt', alt);
            element.setAttribute('ar', '');
            element.setAttribute('ar-modes', 'webxr scene-viewer quick-look');
            // The scan is in real size: a mug stays a mug on the table, no pinching it into a vase.
            element.setAttribute('ar-scale', 'fixed');
            element.setAttribute('ar-placement', 'floor');
            element.setAttribute('camera-controls', '');
            element.setAttribute('touch-action', 'pan-y');
            element.setAttribute('shadow-intensity', '1');
            element.setAttribute('environment-image', 'neutral');
            element.setAttribute('interaction-prompt', 'none');
            if (! still) {
                element.setAttribute('auto-rotate', '');
            }
            element.className = 'block size-full bg-transparent';

            // model-viewer shows this button itself, and only where AR works.
            const button = document.createElement('button');
            button.type = 'button';
            button.slot = 'ar-button';
            button.textContent = texts.place;
            button.className = 'fill-btn absolute bottom-5 left-1/2 inline-flex min-h-[52px] -translate-x-1/2 items-center rounded-full bg-ink px-[30px] text-[14px] font-medium tracking-[0.03em] whitespace-nowrap text-linen hover:bg-rose hover:text-ink';
            element.append(button);

            element.addEventListener('load', () => {
                this.loading = false;
                this.ready = true;
                this.canAr = element.canActivateAR;
            });
            element.addEventListener('error', () => {
                this.loading = false;
                this.failed = true;
            });

            this.$refs.stage.append(element);

            return element;
        },
    };
};
