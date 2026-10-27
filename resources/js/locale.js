/*
 * The visitor's language choice. A click on the language switch, or on either button of the bar that offers
 * the other language, remembers that language in a cookie; from then on the server offers nothing from
 * Accept-Language (Localization\Support\Locales::preferred). The links stay plain links, so without
 * JavaScript the switch still works and only the offer keeps showing up.
 */
const COOKIE = 'mellowaura-locale';
const MAX_AGE = 365 * 24 * 60 * 60;

export default function rememberLocaleChoice() {
    document.addEventListener('click', (event) => {
        const locale = event.target.closest('[data-locale-choice]')?.dataset.localeChoice;

        if (!locale) {
            return;
        }

        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${COOKIE}=${encodeURIComponent(locale)}; Max-Age=${MAX_AGE}; Path=/; SameSite=Lax${secure}`;
    });
}
