/**
 * Live, interactive screen for one cloud phone, via VMOS's Web H5 SDK.
 *
 * The SDK bundle is ~1.7MB, so it is imported dynamically: a customer who only
 * wants to change a phone number never downloads it. Everything here runs
 * against a token minted server-side and scoped to a single padCode — the
 * account's API keys never reach the browser.
 */
export default function liveScreen({ tokenUrl, csrf }) {
    return {
        engine: null,
        state: 'idle', // idle | connecting | live | error
        error: '',
        hint: '',
        muted: true,
        fullscreen: false,

        get isLive() {
            return this.state === 'live';
        },

        get statusLabel() {
            return {
                idle: 'Not connected',
                connecting: 'Connecting…',
                live: 'Live',
                error: 'Disconnected',
            }[this.state];
        },

        async connect() {
            if (this.state === 'connecting' || this.state === 'live') return;

            this.state = 'connecting';
            this.error = '';
            this.hint = 'Asking VMOS for a session…';

            let session;
            try {
                const res = await fetch(tokenUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                });

                // An expired session redirects to the login page, so a non-JSON
                // reply means "sign in again" rather than a device problem.
                if (!res.headers.get('content-type')?.includes('application/json')) {
                    throw new Error('Your session expired. Refresh the page and sign in again.');
                }

                session = await res.json();

                if (!res.ok) throw new Error(session.error || 'Could not start a session.');
            } catch (e) {
                this.state = 'error';
                this.error = e.message || 'Could not reach the server.';
                return;
            }

            this.hint = 'Loading the player…';

            let ArmcloudEngine;
            try {
                ({ ArmcloudEngine } = await import('armcloud-rtc'));
            } catch (e) {
                this.state = 'error';
                this.error = 'The screen player failed to load. Refresh the page and try again.';
                return;
            }

            if (!ArmcloudEngine.isSupported()) {
                this.state = 'error';
                this.error = 'This browser can\'t stream the screen. Try Chrome, Edge or Safari.';
                return;
            }

            this.hint = 'Connecting to the device…';

            this.engine = new ArmcloudEngine({
                baseUrl: session.baseUrl,
                token: session.token,
                viewId: this.$refs.stage.id,
                retryCount: 2,
                retryTime: 2000,
                enableMicrophone: false,
                enableCamera: false,
                deviceInfo: {
                    padCode: session.padCode,
                    userId: session.userId,
                    // 'pad' routes typing to the phone's own keyboard, which is
                    // what people expect when they tap a text field on screen.
                    keyboard: 'pad',
                    saveCloudClipboard: true,
                    autoRecoveryTime: 300,
                },
                callbacks: {
                    onConnectSuccess: () => {
                        this.state = 'live';
                        this.hint = '';
                        this.resize();
                    },
                    onConnectFail: ({ code, msg } = {}) => {
                        this.state = 'error';
                        this.error = `Couldn't connect to the device${msg ? `: ${msg}` : ''}${code ? ` (code ${code})` : ''}.`;
                    },
                    onErrorMessage: ({ msg } = {}) => {
                        if (msg) this.error = msg;
                    },
                    // Fires when the phone is idle long enough that VMOS drops
                    // the stream to save bandwidth — not a failure.
                    onAutoRecoveryTime: () => {
                        this.state = 'idle';
                        this.hint = 'Disconnected after a period of inactivity.';
                    },
                    onUserLeave: () => {
                        this.state = 'idle';
                    },
                },
            });

            this.engine.start();
        },

        disconnect() {
            try {
                this.engine?.stop();
            } catch (e) {
                // Already gone; nothing to clean up.
            }

            this.engine = null;
            this.state = 'idle';
            this.hint = '';
        },

        toggleSound() {
            if (!this.engine) return;

            this.muted = !this.muted;
            this.muted ? this.engine.muted() : this.engine.unmuted();
        },

        toggleFullscreen() {
            const box = this.$refs.frame;
            if (!box) return;

            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                box.requestFullscreen?.();
            }
        },

        /** The SDK needs to be told the container size when the layout moves. */
        resize() {
            const stage = this.$refs.stage;
            if (this.engine && stage) {
                this.engine.setViewSize(stage.clientWidth, stage.clientHeight);
            }
        },

        // Hardware keys, sent as Android keycodes.
        pressBack() { this.engine?.triggerKeyboardShortcut(0, 4); },
        pressHome() { this.engine?.triggerKeyboardShortcut(0, 3); },
        pressRecents() { this.engine?.triggerKeyboardShortcut(0, 187); },

        destroy() {
            this.disconnect();
        },
    };
}
