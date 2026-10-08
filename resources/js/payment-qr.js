import QRCode from 'qrcode';

/**
 * Renders a scannable QR code for a USDT receiving address onto a <canvas>.
 * Encodes the plain address — every TRC20/BEP20 wallet we've checked treats
 * a scanned address as "fill in the recipient", which is the one behavior
 * that needs to work across wallets; there's no standardized payment-URI
 * scheme shared by both networks the way bitcoin: is for BTC.
 */
export default (address) => ({
    address,

    init() {
        QRCode.toCanvas(this.$refs.canvas, this.address, {
            width: 180,
            margin: 1,
        }).catch(() => {
            // Canvas just stays blank — the address text next to it is the fallback.
        });
    },
});
