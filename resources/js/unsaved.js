// Unsaved changes in the panel (MA-251). Every form that sends something with POST remembers what it held when the page
// opened. A changed one is marked „niezapisane” and its save button sticks to the bottom of the screen while the form is in view.
// Saving another form or following a link asks first, in the page and not in a browser dialog; closing the tab gets the
// browser's own question. The forms themselves save exactly as before: nothing here talks to a controller.

const EDITABLE = 'input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=reset]), textarea, select';
// A save area taller than this is the whole card, not a row with the button: sticking it would only make the page jump.
const STICKY_MAX = 160;

const fields = (form) => [...form.elements].filter((field) => field.matches(EDITABLE) && field.name);

const snapshot = (form) => JSON.stringify(fields(form).map((field) => {
    if (field.type === 'checkbox' || field.type === 'radio') {
        return [field.name, field.value, field.checked];
    }

    if (field.type === 'file') {
        return [field.name, [...field.files].map((file) => `${file.name}:${file.size}`)];
    }

    if (field.multiple && field.options) {
        return [field.name, [...field.selectedOptions].map((option) => option.value)];
    }

    return [field.name, field.value];
}));

// What the question calls the form: its own name, its first heading, or the screen.
const nameOf = (form, fallback) => (form.dataset.unsavedName
    ?? form.querySelector('h2, h3, legend')?.textContent
    ?? form.closest('section')?.querySelector('h2, h3')?.textContent
    ?? fallback).trim().replace(/\s+/g, ' ');

const say = (text, form) => text.replace(':form', form);

export default (texts) => ({
    forms: [],
    leaving: false,
    question: null,

    init() {
        this.forms = [...this.$el.querySelectorAll('form')]
            .filter((form) => (form.getAttribute('method') ?? '').toLowerCase() === 'post' && form.dataset.unsaved !== 'off' && fields(form).length > 0);

        // After the components inside the forms have filled them in, so what they render counts as the starting point.
        this.$nextTick(() => this.forms.forEach((form) => {
            form.unsavedSnapshot = snapshot(form);
            const check = () => this.mark(form, snapshot(form) !== form.unsavedSnapshot);
            form.addEventListener('input', check);
            form.addEventListener('change', check);
            // Rows added or removed by a script change the form as much as typing does.
            new MutationObserver(check).observe(form, { childList: true, subtree: true });
        }));

        document.addEventListener('submit', (event) => this.submitting(event), true);
        document.addEventListener('click', (event) => this.following(event), true);
        window.addEventListener('beforeunload', (event) => {
            if (! this.leaving && this.dirty().length > 0) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
        // Back from the next page through the browser's cache: the form is what it was, but the page is not leaving any more.
        window.addEventListener('pageshow', () => {
            this.leaving = false;
        });
    },

    dirty(except = null) {
        return this.forms.filter((form) => form !== except && form.hasAttribute('data-dirty'));
    },

    mark(form, changed) {
        if (changed === form.hasAttribute('data-dirty')) {
            return;
        }

        form.toggleAttribute('data-dirty', changed);
        const area = this.saveArea(form);

        if (changed && area) {
            // A lone button keeps its own colours; a row around it gets the glass.
            area.dataset.unsavedSave = area.matches('button, input') ? 'button' : 'row';
        } else {
            area?.removeAttribute('data-unsaved-save');
        }
        this.$refs.unsavedStatus.textContent = changed ? say(texts.marked, nameOf(form, texts.screen)) : '';
    },

    // The part of the form that holds its save button, if it is a row small enough to stick to the bottom of the screen.
    saveArea(form) {
        const buttons = [...form.querySelectorAll('button:not([type=button]):not([type=reset]), input[type=submit]')]
            .filter((button) => ! button.hasAttribute('formaction'));
        let area = buttons.at(-1);

        while (area && area.parentElement !== form) {
            area = area.parentElement;
        }

        return area && area.offsetHeight <= STICKY_MAX ? area : null;
    },

    submitting(event) {
        const form = event.target;

        if (this.dirty(form).length === 0) {
            this.leaving = true;

            return;
        }

        event.preventDefault();
        this.ask(form, this.dirty(form)[0], () => {
            this.leaving = true;
            form.requestSubmit(event.submitter && form.contains(event.submitter) ? event.submitter : undefined);
        });
    },

    following(event) {
        const link = event.target.closest?.('a[href]');

        if (! link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
            || link.target === '_blank' || link.hasAttribute('download') || this.dirty().length === 0) {
            return;
        }

        const to = new URL(link.href, location.href);

        // A jump within the same page loses nothing.
        if (to.origin === location.origin && to.pathname === location.pathname && to.search === location.search && to.hash) {
            return;
        }

        event.preventDefault();
        this.ask(null, this.dirty()[0], () => {
            this.leaving = true;
            location.href = link.href;
        });
    },

    // The question appears in place: above the save button that was pressed, or at the top of the screen for a link.
    ask(form, unsaved, proceed) {
        this.question?.remove();
        const name = nameOf(unsaved, texts.screen);
        const box = this.$refs.unsavedTemplate.content.firstElementChild.cloneNode(true);
        box.querySelector('[data-text]').textContent = say(texts.question, name);
        box.querySelector('[data-go]').textContent = say(texts.go, name);
        box.querySelector('[data-drop]').textContent = say(texts.drop, name);

        box.querySelector('[data-go]').addEventListener('click', () => {
            box.remove();
            unsaved.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            fields(unsaved).find((field) => field.offsetParent !== null)?.focus({ preventScroll: true });
        });
        box.querySelector('[data-drop]').addEventListener('click', () => {
            box.remove();
            this.dirty(form).forEach((other) => {
                other.reset();
                this.mark(other, false);
            });
            proceed();
        });

        const anchor = form ? (this.saveArea(form) ?? form.lastElementChild) : null;

        if (anchor) {
            anchor.before(box);
        } else {
            this.$el.prepend(box);
        }

        this.question = box;
        box.querySelector('[data-go]').focus({ preventScroll: true });
        box.scrollIntoView({ block: 'nearest' });
    },
});
