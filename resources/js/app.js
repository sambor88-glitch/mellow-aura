import Alpine from 'alpinejs';
import cart from './cart';
import checkout from './checkout';

window.Alpine = Alpine;

Alpine.store('cart', cart);
Alpine.data('checkout', checkout);
Alpine.start();
