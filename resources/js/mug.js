/*
 * The mug configurator: the text appears on the photo letter by letter, stays within the lines and
 * characters from the panel, and each new letter sounds like a stamp going into clay. The server checks
 * the text again; the page only keeps typing within the limits.
 */
const SOUND_KEY = 'ma-sound';

// 1 linię, 2–4 linie, 5 linii; 12–14 take the last form too.
const plural = (count, one, few, many) => {
    if (count === 1) {
        return one;
    }

    return [2, 3, 4].includes(count % 10) && ![12, 13, 14].includes(count % 100) ? few : many;
};

export default ({ maxLines, maxChars, prices, glazes, size, glaze, ink }) => {
    // The audio context and the 3D mug stay outside Alpine's reactive state.
    let audio = null;
    let mug = null;

    return {
        text: '',
        size,
        glaze,
        sound: true,
        // The field has focus: on a phone the small preview shows above it and the bottom bar steps aside.
        writing: false,
        limitNotice: '',
        // The photo with the text on it until the 3D mug has loaded; then either one, by choice.
        view: 'photo',
        has3d: false,

        init() {
            try {
                this.sound = window.localStorage.getItem(SOUND_KEY) !== 'off';
            } catch {
                // Private mode without storage: the sound stays on.
            }

            this.load3d();
        },

        // The 3D mug (resources/js/mug3d.js) comes as its own file, only here, and only with WebGL and full motion.
        async load3d() {
            if (!this.$refs.stage3d || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            try {
                const { default: createMug, webglWorks } = await import('./mug3d');

                if (!webglWorks()) {
                    return;
                }

                mug = createMug(this.$refs.stage3d, { ink, glaze: this.glazeHex });
                mug.setText(this.text.split('\n'), false);
                this.has3d = true;
                this.view = '3d';
                this.$watch('text', (value) => mug.setText(value.split('\n'), true));
                this.$watch('glaze', () => mug.setGlaze(this.glazeHex));
            } catch {
                // The photo preview already works; the 3D mug is only a bonus.
            }
        },

        get preview() {
            const typed = this.text.trim() !== '';

            return (typed ? this.text.split('\n') : ['TWÓJ NAPIS']).map((line, row) => ({
                key: (typed ? 't' : 'p') + row,
                letters: Array.from(line.toUpperCase()).map((letter, column) => ({ key: (typed ? 't' : 'p') + row + '-' + column, letter })),
            }));
        },

        get counter() {
            return this.text.split('\n').map((line) => line.length + '/' + maxChars).join('  ·  ');
        },

        get lineInfo() {
            const count = this.text.split('\n').length;

            return count === 1 ? 'Enter przenosi wyraz do nowej linii' : `${count} ${count < 5 ? 'linie' : 'linii'} z ${maxLines} możliwych`;
        },

        get price() {
            return this.$store.cart.format(prices[this.size] ?? 0);
        },

        get glazeName() {
            return glazes[this.glaze]?.name ?? '';
        },

        get glazeHex() {
            return glazes[this.glaze]?.hex ?? 'transparent';
        },

        limit(value) {
            return value.replace(/\r/g, '').split('\n').slice(0, maxLines).map((line) => line.slice(0, maxChars)).join('\n');
        },

        typed(event) {
            const field = event.target;
            const next = this.limit(field.value);

            if (next.replace(/\s/g, '').length > this.text.replace(/\s/g, '').length) {
                this.stamp();
            }

            if (next !== field.value) {
                const caret = Math.min(field.selectionStart, next.length);
                field.value = next;
                field.setSelectionRange(caret, caret);
                this.announceLimit();
            }

            this.text = next;
        },

        announceLimit() {
            // Emptied first, so the same sentence is read again after the next letter that doesn't fit.
            this.limitNotice = '';
            this.$nextTick(() => {
                this.limitNotice = `Zmieszczę ${maxLines} ${plural(maxLines, 'linię', 'linie', 'linii')} po ${maxChars} ${plural(maxChars, 'znak', 'znaki', 'znaków')}`;
            });
        },

        startWriting() {
            this.writing = true;

            // After the keyboard has opened, bring the small preview and the field into view together.
            setTimeout(() => {
                if (this.writing && this.$refs.mini.offsetParent !== null) {
                    this.$refs.mini.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
            }, 350);
        },

        focusText() {
            this.$refs.text.focus();
        },

        setText(value) {
            this.text = value;
            this.$refs.text.value = value;
        },

        split() {
            const words = this.text.replace(/\n/g, ' ').split(/\s+/).filter(Boolean);

            if (words.length < 2) {
                this.$store.cart.say('Do podziału potrzebne są co najmniej dwa wyrazy');

                return;
            }

            const lines = [];
            let current = '';

            words.forEach((word) => {
                if (!current) {
                    current = word;
                } else if ((current + ' ' + word).length <= maxChars) {
                    current += ' ' + word;
                } else {
                    lines.push(current);
                    current = word;
                }
            });
            lines.push(current);

            this.setText(this.limit(lines.join('\n')));
        },

        async add(form) {
            if (await this.$store.cart.send(form)) {
                this.setText('');
            }
        },

        toggleSound() {
            this.sound = !this.sound;

            try {
                window.localStorage.setItem(SOUND_KEY, this.sound ? 'on' : 'off');
            } catch {
                // Not remembered, but it works for this visit.
            }
        },

        // A dull thump: a falling sine for the stamp and a short burst of muffled noise for the clay.
        stamp() {
            const Context = window.AudioContext || window.webkitAudioContext;

            if (!this.sound || !Context) {
                return;
            }

            try {
                audio ??= new Context();

                if (audio.state === 'suspended') {
                    audio.resume();
                }

                const now = audio.currentTime;
                const tone = audio.createOscillator();
                const toneGain = audio.createGain();
                tone.frequency.setValueAtTime(140, now);
                tone.frequency.exponentialRampToValueAtTime(50, now + 0.09);
                toneGain.gain.setValueAtTime(0.0001, now);
                toneGain.gain.exponentialRampToValueAtTime(0.32, now + 0.004);
                toneGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.12);
                tone.connect(toneGain);
                toneGain.connect(audio.destination);
                tone.start(now);
                tone.stop(now + 0.13);

                const length = Math.floor(audio.sampleRate * 0.035);
                const buffer = audio.createBuffer(1, length, audio.sampleRate);
                const samples = buffer.getChannelData(0);

                for (let i = 0; i < length; i++) {
                    samples[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / length, 2);
                }

                const noise = audio.createBufferSource();
                const muffle = audio.createBiquadFilter();
                const noiseGain = audio.createGain();
                noise.buffer = buffer;
                muffle.type = 'lowpass';
                muffle.frequency.value = 850;
                noiseGain.gain.value = 0.22;
                noise.connect(muffle);
                muffle.connect(noiseGain);
                noiseGain.connect(audio.destination);
                noise.start(now);
            } catch {
                // No sound is better than a broken page.
            }
        },
    };
};
