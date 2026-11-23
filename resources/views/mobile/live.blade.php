<!DOCTYPE html>
<html lang="en" class="h-full bg-black">
<head>
    <meta charset="utf-8">
    {{-- No zoom: pinches and double-taps belong to the phone screen, not the page. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>Live screen</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html, body { overscroll-behavior: none; -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; }
    </style>
</head>
{{-- Shown only inside the iPhone app's embedded view. The app draws its own
     native controls (back/home/recents, keyboard, camera) and drives this page
     through window.vhonixLive; state changes go back via the "vhonix" message
     handler. In a normal browser the fallback controls below still work. --}}
<body class="h-full overflow-hidden bg-black text-white">
<div class="flex h-full flex-col"
     x-data="liveScreen({ tokenUrl: @js($tokenUrl), csrf: '' })"
     x-init="
        window.vhonixLive = $data;
        const post = (msg) => window.webkit?.messageHandlers?.vhonix?.postMessage(msg);
        $watch('state', s => post({ type: 'state', state: s, error: error, hint: hint }));
        $watch('error', e => post({ type: 'state', state: state, error: e, hint: hint }));
        $watch('cameraError', e => e && post({ type: 'cameras', cameras: JSON.parse(JSON.stringify(cameras)), selected: cameraId, error: e }));
        $watch('cameras', c => post({ type: 'cameras', cameras: JSON.parse(JSON.stringify(c)), selected: cameraId, error: cameraError }));
        $nextTick(() => connect());
     "
     @resize.window.debounce.200ms="resize()"
     @pagehide.window="destroy()">

    <div x-ref="frame" class="relative flex min-h-0 flex-1 items-center justify-center">
        <div class="aspect-[9/16] h-full max-w-full">
            <div x-ref="stage" id="pad-stage" class="h-full w-full"></div>
        </div>

        <div x-show="!isLive" class="absolute inset-0 flex flex-col items-center justify-center gap-3 px-8 text-center">
            <template x-if="state === 'connecting'">
                <svg class="h-8 w-8 animate-spin text-white/70" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0110 10h-3a7 7 0 00-7-7V2z" />
                </svg>
            </template>
            <p x-show="hint" x-text="hint" class="text-sm text-white/60"></p>
            <p x-show="error" x-text="error" x-cloak class="max-w-sm rounded-xl bg-red-500/15 px-4 py-3 text-sm text-red-200"></p>
            <button x-show="state === 'idle' || state === 'error'" x-cloak @click="connect()"
                    class="rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-black">
                <span x-text="state === 'error' ? 'Try again' : 'Connect'"></span>
            </button>
        </div>
    </div>

    {{-- Browser fallback only; the app hides this with ?native=1 and uses its own toolbar. --}}
    @unless (request()->boolean('native'))
        <div x-show="isLive" x-cloak class="flex items-center justify-center gap-3 bg-black/80 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <button @click="pressBack()" class="rounded-lg bg-white/10 px-4 py-2 text-sm">◁</button>
            <button @click="pressHome()" class="rounded-lg bg-white/10 px-4 py-2 text-sm">◯</button>
            <button @click="pressRecents()" class="rounded-lg bg-white/10 px-4 py-2 text-sm">▢</button>
        </div>
    @endunless
</div>
</body>
</html>
