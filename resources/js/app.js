import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import liveScreen from './live-screen';
import paymentQr from './payment-qr';

Alpine.plugin(collapse);

// The live screen's own SDK is loaded on demand from inside this component.
Alpine.data('liveScreen', liveScreen);
Alpine.data('paymentQr', paymentQr);

window.Alpine = Alpine;

Alpine.start();
