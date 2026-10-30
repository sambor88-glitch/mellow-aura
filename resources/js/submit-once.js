/*
 * A form marked data-submit-once goes to the server once: a second tap while the next page loads does nothing,
 * so a BLIK code, a booking or a message is never sent twice (MA-282). While it waits the form is aria-busy and
 * its submit button looks pressed (app.css). A form whose own script stopped the submit (a missing field) stays free.
 * Back from the next page through the browser's cache the form is usable again.
 */
export default function submitOnce() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (! form.hasAttribute('data-submit-once') || event.defaultPrevented) {
            return;
        }

        if (form.hasAttribute('data-sending')) {
            event.preventDefault();

            return;
        }

        form.setAttribute('data-sending', '');
        form.setAttribute('aria-busy', 'true');
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            document.querySelectorAll('form[data-sending]').forEach((form) => {
                form.removeAttribute('data-sending');
                form.removeAttribute('aria-busy');
            });
        }
    });
}
