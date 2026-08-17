/**
 * Live, interactive screen for one cloud phone, via VMOS's Web H5 SDK.
 *
 * The SDK bundle is ~1.7MB, so it is imported dynamically: a customer who only
 * wants to change a phone number never downloads it. Everything here runs
 * against a token minted server-side and scoped to a single padCode — the
 * account's API keys never reach the browser.
 */
/** Nothing has connected after this long, so stop pretending it might. */
const CONNECT_TIMEOUT_MS = 45000;

export default function liveScreen({ tokenUrl, csrf }) {
    return {
        engine: null,
        state: 'idle', // idle | connecting | live | error
        error: '',
        hint: '',
        muted: true,
        fullscreen: false,
        watchdog: null,
        // A readable trail of what the SDK reported, shown when a connection
        // fails. Without it a stalled handshake is just a spinner forever.
        log: [],
        showLog: false,

        note(message) {
            const at = new Date().toLocaleTimeString();
            this.log.push(`${at}  ${message}`);
            if (this.log.length > 25) this.log.shift();
        },

        copyLog() {
            navigator.clipboard?.writeText(this.log.join('\n'));
        },

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
            this.log = [];
            this.hint = 'Asking VMOS for a session…';

            // Streaming needs a secure page. On http:// the browser withholds
            // parts of the WebRTC stack, and the handshake tends to stall with
            // no error at all — worth saying up front rather than after 45s.
            if (!window.isSecureContext) {
                this.note('WARNING: page is not served over HTTPS — WebRTC is restricted on insecure origins.');
            }

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
                this.fail(e.message || 'Could not reach the server.');
                return;
            }

            this.note(`session issued for ${session.padCode} via ${session.baseUrl}`);
            this.hint = 'Loading the player…';

            let ArmcloudEngine;
            try {
                ({ ArmcloudEngine } = await import('armcloud-rtc'));
            } catch (e) {
                this.fail('The screen player failed to load. Refresh the page and try again.');
                return;
            }

            if (!ArmcloudEngine.isSupported()) {
                this.fail('This browser can\'t stream the screen. Try Chrome, Edge or Safari.');
                return;
            }

            this.hint = 'Connecting to the device…';

            // The SDK leans on WebRTC APIs the browser withholds on insecure
            // origins, and it reports that by throwing rather than through a
            // callback. Without this the failure is invisible: no callback
            // fires, and the panel just sits on "Connecting" until the
            // watchdog. Everything from construction to start() is wrapped.
            // start() also fails asynchronously, which a try/catch can't see.
            this.captureAsyncErrors();

            try {
                this.startEngine(ArmcloudEngine, session);
            } catch (e) {
                this.note(`SDK threw: ${e?.name || 'Error'}: ${e?.message || e}`);
                this.fail(
                    window.isSecureContext
                        ? `The screen player failed to start: ${e?.message || 'unknown error'}`
                        : 'The screen player couldn\'t start because this page isn\'t using HTTPS. Browsers block video streaming on insecure pages — enable SSL on your domain and try again.'
                );
            }
        },

        startEngine(ArmcloudEngine, session) {
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
                    onInit: ({ code, msg } = {}) => {
                        this.note(`init: code=${code}${msg ? ` msg=${msg}` : ''}`);
                    },
                    onSocketCallback: ({ code, msg } = {}) => {
                        this.note(`signalling: code=${code}${msg ? ` msg=${msg}` : ''}`);
                    },
                    onConnectionStateChanged: ({ state, code, msg } = {}) => {
                        this.note(`state=${state}${code ? ` code=${code}` : ''}${msg ? ` msg=${msg}` : ''}`);
                    },
                    onConnectSuccess: () => {
                        this.note('joined the room');
                        this.hint = 'Waiting for the first frame…';
                    },
                    // The room is joined before any video arrives, so this —
                    // not onConnectSuccess — is the moment there's a picture.
                    onRenderedFirstFrame: () => {
                        this.note('first frame rendered');
                        this.releaseAsyncErrors();
                        this.clearWatchdog();
                        this.state = 'live';
                        this.hint = '';
                        this.resize();
                    },
                    onConnectFail: ({ code, msg } = {}) => {
                        this.note(`connect failed: code=${code}${msg ? ` msg=${msg}` : ''}`);
                        this.fail(`Couldn't connect to the device${msg ? `: ${msg}` : ''}${code ? ` (code ${code})` : ''}.`);
                    },
                    onErrorMessage: ({ code, msg } = {}) => {
                        this.note(`error: code=${code}${msg ? ` msg=${msg}` : ''}`);
                        if (msg) this.error = msg;
                    },
                    onVideoError: (e) => this.note(`video error: ${e?.msg || 'unknown'}`),
                    onAudioError: (e) => this.note(`audio error: ${e?.msg || 'unknown'}`),
                    // The browser refused to autoplay; a click is needed.
                    onAutoplayFailed: () => {
                        this.note('autoplay blocked');
                        this.hint = 'Click the screen to start playback.';
                    },
                    // Fires when the phone is idle long enough that VMOS drops
                    // the stream to save bandwidth — not a failure.
                    onAutoRecoveryTime: () => {
                        this.note('dropped after inactivity');
                        this.clearWatchdog();
                        this.state = 'idle';
                        this.hint = 'Disconnected after a period of inactivity.';
                    },
                    onUserLeave: () => {
                        this.note('session ended');
                        this.clearWatchdog();
                        this.state = 'idle';
                    },
                },
            });

            // Nothing above is guaranteed to fire. A stalled handshake reports
            // nothing at all, which is exactly the case worth catching.
            this.watchdog = setTimeout(() => {
                if (this.state !== 'live') {
                    this.fail(
                        window.isSecureContext
                            ? 'The device didn\'t start streaming. This is usually the streaming region (VMOS_SDK_BASE_URL) or a network blocking WebRTC.'
                            : 'The device didn\'t start streaming. This page isn\'t using HTTPS, which browsers require for video streaming — enable SSL and try again.'
                    );
                }
            }, CONNECT_TIMEOUT_MS);

            this.engine.start();
        },

        /** Records rejections thrown inside the SDK while we're connecting. */
        captureAsyncErrors() {
            if (this.rejectionHandler) return;

            this.rejectionHandler = (event) => {
                if (this.state === 'connecting') {
                    this.note(`unhandled: ${event.reason?.message || event.reason}`);
                }
            };

            window.addEventListener('unhandledrejection', this.rejectionHandler);
        },

        releaseAsyncErrors() {
            if (this.rejectionHandler) {
                window.removeEventListener('unhandledrejection', this.rejectionHandler);
                this.rejectionHandler = null;
            }
        },

        fail(message) {
            this.releaseAsyncErrors();
            this.clearWatchdog();
            this.state = 'error';
            this.hint = '';
            this.error = message;
        },

        clearWatchdog() {
            if (this.watchdog) {
                clearTimeout(this.watchdog);
                this.watchdog = null;
            }
        },

        disconnect() {
            this.releaseAsyncErrors();
            this.clearWatchdog();

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
