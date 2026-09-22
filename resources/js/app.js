import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { trackClicks, trackPage } from './analytics';
import aura from './aura';
import beforeAfter from './before-after';
import buyBar from './buy-bar';
import cart from './cart';
import checkout from './checkout';
import consentBanner from './consent';
import favorites from './favorites';
import stickyHeader from './header';
import mugConfigurator from './mug';
import pairUpload from './pair-upload';
import photoPicker from './photos';

window.Alpine = Alpine;

Alpine.plugin(focus);

Alpine.store('cart', cart);
Alpine.store('favorites', favorites);
Alpine.data('beforeAfter', beforeAfter);
Alpine.data('buyBar', buyBar);
Alpine.data('checkout', checkout);
Alpine.data('consentBanner', consentBanner);
Alpine.data('mugConfigurator', mugConfigurator);
Alpine.data('pairUpload', pairUpload);
Alpine.data('photoPicker', photoPicker);
Alpine.data('stickyHeader', stickyHeader);
Alpine.start();
aura();
trackPage();
trackClicks();
