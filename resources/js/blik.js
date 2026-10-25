/*
 * The BLIK page while the customer confirms in the bank's app: a two-minute clock, and a question to the shop
 * every few seconds whether the money is in. The shop answers from the gateway's notification, never from here.
 * When the clock runs out the page reloads, and the server shows the field for a new code.
 */
const POLL_MS = 3000;
// The moments a screen reader hears the time left; every second would drown everything else.
const ANNOUNCE_AT = [90, 60, 30, 10];

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export default ({ seconds, total, statusUrl, texts }) => ({
    left: seconds,
    announced: '',

    get clock() {
        return `${Math.floor(this.left / 60)}:${String(this.left % 60).padStart(2, '0')}`;
    },

    get progress() {
        return Math.round((this.left / total) * 100);
    },

    init() {
        const end = Date.now() + seconds * 1000;

        this.timer = setInterval(() => {
            const left = Math.max(0, Math.ceil((end - Date.now()) / 1000));

            if (left === this.left) {
                return;
            }

            this.left = left;

            if (ANNOUNCE_AT.includes(left)) {
                this.announced = texts.left.replace(':time', this.clock);
            }

            if (left === 0) {
                clearInterval(this.timer);
                // A second's grace, so the server's clock has run out too.
                setTimeout(() => window.location.reload(), 1000);
            }
        }, 250);

        this.poll();
    },

    destroy() {
        clearInterval(this.timer);
    },

    async poll() {
        while (this.left > 0) {
            await sleep(POLL_MS);

            try {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                const { paid, url } = await response.json();

                if (paid) {
                    window.location.assign(url);

                    return;
                }
            } catch {
                // A dropped connection on the phone: the next question goes in a few seconds.
            }
        }
    },
});
