import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { trackClicks, trackPage } from './analytics';
import aura from './aura';
import beforeAfter from './before-after';
import blikWait from './blik';
import briefPhotos from './brief-photos';
import buyBar from './buy-bar';
import cart from './cart';
import checkout from './checkout';
import consentBanner from './consent';
import debugTaps, { debugVariant } from './debug-taps';
import favorites from './favorites';
import firingQuote from './firing';
import stickyHeader from './header';
import rememberLocaleChoice from './locale';
import mugConfigurator from './mug';
import pairUpload from './pair-upload';
import photoPicker from './photos';

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
Alpine.start();
const debug = debugVariant();
if (debug) {
    debugTaps(debug);
}
if (debug !== 'still') {
    aura();
}
trackPage();
trackClicks();
rememberLocaleChoice();
