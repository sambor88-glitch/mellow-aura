import Alpine from 'alpinejs';
import cart from './cart';

window.Alpine = Alpine;

Alpine.store('cart', cart);
Alpine.start();
