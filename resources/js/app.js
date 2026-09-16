import Alpine from 'alpinejs';
import cart from './cart';
import checkout from './checkout';
import consentBanner from './consent';
import mugConfigurator from './mug';
import photoPicker from './photos';

window.Alpine = Alpine;

Alpine.store('cart', cart);
Alpine.data('checkout', checkout);
Alpine.data('consentBanner', consentBanner);
Alpine.data('mugConfigurator', mugConfigurator);
Alpine.data('photoPicker', photoPicker);
Alpine.start();
