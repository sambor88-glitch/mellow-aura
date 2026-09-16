import Alpine from 'alpinejs';
import beforeAfter from './before-after';
import cart from './cart';
import checkout from './checkout';
import consentBanner from './consent';
import mugConfigurator from './mug';
import pairUpload from './pair-upload';
import photoPicker from './photos';

window.Alpine = Alpine;

Alpine.store('cart', cart);
Alpine.data('beforeAfter', beforeAfter);
Alpine.data('checkout', checkout);
Alpine.data('consentBanner', consentBanner);
Alpine.data('mugConfigurator', mugConfigurator);
Alpine.data('pairUpload', pairUpload);
Alpine.data('photoPicker', photoPicker);
Alpine.start();
