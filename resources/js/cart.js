/*
 * The cart drawer. Cart forms post in the background and the server answers with the drawer's
 * fresh content, so Blade stays the only place that renders the cart.
 */
const failure = 'Coś się zacięło po mojej stronie. Twój koszyk jest bezpieczny — spróbuj za chwilę.';

export default {
    count: 0,
    notice: '',
    opened: false,
    trigger: null,
    timer: null,

    init() {
        this.count = Number(document.getElementById('cart')?.dataset.count ?? 0);
    },

    get dialog() {
        return document.getElementById('cart');
    },

    open(trigger) {
        if (this.dialog.open) {
            return;
        }

        this.trigger = trigger ?? document.activeElement;
        this.dialog.showModal();
        this.opened = true;
    },

    closed() {
        this.opened = false;
        this.trigger?.focus();
        this.trigger = null;
    },

    async send(form) {
        const focusKey = document.activeElement?.dataset.focus;
        let response = null;
        let data = null;

        try {
            response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
            });
            data = await response.json();
        } catch {
            data = null;
        }

        if (!response?.ok || !data) {
            this.say(response?.status === 422 && data?.message ? data.message : failure);

            return;
        }

        document.getElementById('cart-content').innerHTML = data.content;
        this.count = data.count;
        this.open(document.activeElement);

        // The pressed button was replaced, so focus its new copy, or × when the line is gone.
        if (focusKey) {
            (this.dialog.querySelector(`[data-focus="${focusKey}"]`) ?? this.dialog.querySelector('[data-focus="close"]'))?.focus();
        }

        this.say(data.notice);
    },

    say(message) {
        clearTimeout(this.timer);
        this.notice = message ?? '';

        if (this.notice) {
            this.timer = setTimeout(() => (this.notice = ''), 2600);
        }
    },

    format(grosze) {
        return (grosze / 100).toFixed(2).replace('.', ',') + ' zł';
    },
};
