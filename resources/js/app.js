import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { trackPage } from './analytics';
import beforeAfter from './before-after';
import blikWait from './blik';
import briefPhotos from './brief-photos';
import buyBar from './buy-bar';
import cart from './cart';
import checkout from './checkout';
import consentBanner from './consent';
import favorites from './favorites';
import firingQuote from './firing';
import stickyHeader from './header';
import hoverPhotos from './hover-photos';
import rememberLocaleChoice from './locale';
import mugConfigurator from './mug';
import pairUpload from './pair-upload';
import photoPicker from './photos';
import submitOnce from './submit-once';
import { pageTransitions, whenShown } from './transitions';
import unsavedChanges from './unsaved';

window.Alpine = Alpine;

Alpine.plugin(focus);

Alpine.store('cart', cart);
Alpine.store('favorites', favorites);
Alpine.data('beforeAfter', beforeAfter);
Alpine.data('blikWait', blikWait);
Alpine.data('briefPhotos', briefPhotos);
Alpine.data('buyBar', buyBar);
Alpine.data('checkout', checkout);
Alpine.data('consentBanner', consentBanner);
Alpine.data('firingQuote', firingQuote);
Alpine.data('mugConfigurator', mugConfigurator);
Alpine.data('pairUpload', pairUpload);
Alpine.data('photoPicker', photoPicker);
Alpine.data('stickyHeader', stickyHeader);
Alpine.data('unsavedChanges', unsavedChanges);
Alpine.start();
// The motion (GSAP, ScrollTrigger, Lenis) is a file of its own that comes after Alpine: in this one it was half of what
// a phone downloaded and ran before the page answered a tap (MA-232). Without it every page is complete and still.
// On a phone it waits for the first touch or scroll, or three seconds after the page loads: on a slow line its 50 kB
// shared the connection with the first photo and held it back (MA-271). Its effects follow scrolling, so nothing is
// missed. A computer starts it at once, as the home hero's layout depends on it.
if (! matchMedia('(prefers-reduced-motion: reduce)').matches) {
    let started = false;
    const motion = () => {
        if (! started) {
            started = true;
            import('./aura').then(({ default: aura }) => aura()).catch(() => {});
        }
    };
    if (matchMedia('(max-width: 760px)').matches) {
        ['touchstart', 'scroll', 'keydown'].forEach((type) => addEventListener(type, motion, { once: true, passive: true }));
        const later = () => setTimeout(motion, 3000);
        if (document.readyState === 'complete') {
            later();
        } else {
            addEventListener('load', later, { once: true });
        }
    } else {
        motion();
    }
}
hoverPhotos();
pageTransitions();
// A page rendered ahead of a click sends its Analytics events only once someone sees it (MA-237).
whenShown(trackPage);
rememberLocaleChoice();
submitOnce();
