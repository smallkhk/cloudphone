@php
    $d = $details ?? [];
    $ready = (bool) $instance->pad_code;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$instance->nickname ?? $instance->sku?->name ?? 'Cloud Phone'"
                       :subtitle="$instance->pad_code ?? 'Provisioning…'">
            <x-slot name="actions">
                <a href="{{ route('instances.index') }}" class="btn-ghost">&larr; All devices</a>
                @if ($ready)
                    <form method="POST" action="{{ route('instances.screenshot', $instance) }}">
                        @csrf
                        <button class="btn-secondary">Refresh screen</button>
                    </form>
                    <form method="POST" action="{{ route('instances.restart', $instance) }}">
                        @csrf
                        <button class="btn-secondary">Restart</button>
                    </form>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    @unless ($ready)
        <div class="card p-8 text-center">
            <p class="text-sm text-ink-600">This device is still being provisioned. Controls appear once VMOS assigns it.</p>
        </div>
    @else
        @if ($detailsError)
            <div class="mb-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
                Couldn't load live device info from VMOS: {{ $detailsError }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Screen + identity ------------------------------------------ --}}
            <div class="space-y-6">
                <div class="card overflow-hidden">
                    <div class="relative aspect-[9/16] bg-gradient-to-br from-ink-900 to-ink-950">
                        @if ($instance->screenshot_url)
                            <img src="{{ $instance->screenshot_url }}" alt="Screen" class="h-full w-full object-contain">
                        @else
                            <div class="flex h-full items-center justify-center text-xs text-ink-500">No preview yet</div>
                        @endif
                        <span class="absolute left-3 top-3 {{ $instance->online ? 'badge bg-emerald-500/90 text-white ring-emerald-400/30' : 'badge bg-ink-800/90 text-ink-300 ring-white/10' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $instance->online ? 'bg-white' : 'bg-ink-400' }}"></span>
                            {{ $instance->statusLabel() }}
                        </span>
                    </div>
                </div>

                <div class="card p-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-400">Device identity</h2>
                    <dl class="mt-4 space-y-2.5 text-sm">
                        @foreach ([
                            'Model' => $d['padType'] ?? $instance->sku?->name,
                            'Android' => $d['androidVersion'] ?? $instance->sku?->android_version,
                            'Phone number' => $d['phoneNumber'] ?? null,
                            'IMEI' => $d['padImei'] ?? null,
                            'Carrier' => $d['simIso'] ?? null,
                            'SIM country' => $d['simCountry'] ?? null,
                            'Public IP' => $d['publicIp'] ?? null,
                            'IP location' => $d['ipAddress'] ?? null,
                            'Timezone' => $d['timeZone'] ?? null,
                            'Language' => $d['explain'] ?? null,
                            'GPS' => isset($d['latitude'], $d['longitude']) ? $d['latitude'].', '.$d['longitude'] : null,
                            'WLAN MAC' => $d['wlanMac'] ?? null,
                            'Bluetooth' => $d['bluetoothAddress'] ?? null,
                        ] as $label => $value)
                            @if (filled($value))
                                <div class="flex items-start justify-between gap-3">
                                    <dt class="flex-none text-ink-500">{{ $label }}</dt>
                                    <dd class="break-all text-right font-medium text-ink-900">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                        @if ($instance->expires_at)
                            <div class="flex justify-between gap-3 border-t border-ink-100 pt-2.5">
                                <dt class="text-ink-500">Expires</dt>
                                <dd class="font-medium {{ $instance->isExpired() ? 'text-red-600' : 'text-ink-900' }}">
                                    {{ $instance->expires_at->format('d M Y') }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Controls --------------------------------------------------- --}}
            <div class="space-y-6 lg:col-span-2" x-data="{ tab: 'screen' }">
                <div class="card overflow-hidden p-1.5">
                    <div class="flex flex-wrap gap-1">
                        @foreach ([
                            'screen' => 'Live screen',
                            'identity' => 'Phone number & location',
                            'proxy' => 'Proxy',
                            'apps' => 'Apps',
                            'drive' => 'Cloud Drive',
                            'advanced' => 'Advanced',
                        ] as $key => $label)
                            <button @click="tab = '{{ $key }}'"
                                    :class="tab === '{{ $key }}' ? 'bg-brand-50 text-brand-700' : 'text-ink-600 hover:bg-ink-100'"
                                    class="rounded-lg px-3.5 py-2 text-sm font-medium transition-colors">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Live screen tab --}}
                <div x-show="tab === 'screen'"
                     x-data="liveScreen({ tokenUrl: '{{ route('instances.screen-token', $instance) }}', csrf: '{{ csrf_token() }}' })"
                     x-init="$watch('tab', value => { if (value !== 'screen') disconnect() })"
                     @resize.window.debounce.200ms="resize()"
                     @beforeunload.window="destroy()"
                     class="card overflow-hidden">

                    <div class="flex flex-wrap items-center gap-3 border-b border-ink-100 p-4">
                        <span class="flex items-center gap-2 text-sm font-medium text-ink-700">
                            <span class="h-2 w-2 rounded-full"
                                  :class="{ 'bg-emerald-500': state === 'live', 'bg-amber-400 animate-pulse': state === 'connecting', 'bg-ink-300': state === 'idle', 'bg-red-500': state === 'error' }"></span>
                            <span x-text="statusLabel"></span>
                        </span>

                        <div class="ml-auto flex flex-wrap items-center gap-2">
                            <template x-if="!isLive && state !== 'connecting'">
                                <button @click="connect()" class="btn-primary btn-sm">Connect</button>
                            </template>
                            <template x-if="isLive">
                                <div class="flex items-center gap-2">
                                    <button @click="toggleSound()" class="btn-secondary btn-sm"
                                            x-text="muted ? 'Unmute' : 'Mute'"></button>
                                    <button @click="toggleFullscreen()" class="btn-secondary btn-sm">Fullscreen</button>
                                    <button @click="disconnect()" class="btn-secondary btn-sm">Disconnect</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Stage. 9:16 keeps the phone's aspect ratio; the SDK draws into #pad-stage. --}}
                    <div x-ref="frame" class="relative bg-ink-950">
                        <div class="mx-auto aspect-[9/16] w-full max-w-[22rem]">
                            <div x-ref="stage" id="pad-stage" class="h-full w-full"></div>
                        </div>

                        {{-- Overlay: shown until the stream is live --}}
                        <div x-show="!isLive" class="absolute inset-0 flex flex-col items-center justify-center gap-3 px-6 text-center">
                            <template x-if="state === 'connecting'">
                                <svg class="h-7 w-7 animate-spin text-brand-400" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                                    <path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0110 10h-3a7 7 0 00-7-7V2z" />
                                </svg>
                            </template>

                            <template x-if="state === 'idle'">
                                <div>
                                    <svg class="mx-auto h-9 w-9 text-ink-600" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="6" y="2" width="12" height="20" rx="2.5" />
                                        <path stroke-linecap="round" d="M10.5 18.5h3" />
                                    </svg>
                                    <p class="mt-3 text-sm font-medium text-white">Take control of this phone</p>
                                    <p class="mt-1 text-xs text-ink-400">Tap and swipe the screen just like a real device.</p>
                                </div>
                            </template>

                            <p x-show="hint" x-text="hint" class="text-xs text-ink-400"></p>
                            <p x-show="error" x-text="error" x-cloak
                               class="max-w-sm rounded-xl bg-red-500/10 px-4 py-2.5 text-xs text-red-300 ring-1 ring-inset ring-red-400/20"></p>

                            <div class="flex items-center gap-2">
                                <button x-show="state === 'idle' || state === 'error'" @click="connect()" class="btn-primary btn-sm mt-1">
                                    <span x-text="state === 'error' ? 'Try again' : 'Connect'"></span>
                                </button>
                                <button x-show="log.length" @click="showLog = !showLog" x-cloak
                                        class="mt-1 rounded-lg px-2.5 py-1.5 text-xs text-ink-400 hover:text-white">
                                    <span x-text="showLog ? 'Hide details' : 'Details'"></span>
                                </button>
                            </div>

                            {{-- Connection trail. A stalled handshake reports nothing
                                 on screen, so this is what makes it diagnosable. --}}
                            <div x-show="showLog" x-cloak class="w-full max-w-md">
                                <pre class="max-h-40 overflow-auto rounded-xl bg-black/40 p-3 text-left font-mono text-[10px] leading-relaxed text-ink-300"><template x-for="line in log" :key="line"><span x-text="line + '\n'"></span></template></pre>
                                <button @click="copyLog()" class="mt-2 text-xs text-ink-400 hover:text-white">Copy</button>
                            </div>
                        </div>
                    </div>

                    {{-- Hardware keys --}}
                    <div x-show="isLive" x-cloak class="flex items-center justify-center gap-2 border-t border-ink-100 p-3">
                        <button @click="pressBack()" class="btn-secondary btn-sm" title="Back">◁ Back</button>
                        <button @click="pressHome()" class="btn-secondary btn-sm" title="Home">◯ Home</button>
                        <button @click="pressRecents()" class="btn-secondary btn-sm" title="Recent apps">▢ Recents</button>
                    </div>

                    <p class="border-t border-ink-100 px-4 py-3 text-xs leading-relaxed text-ink-500">
                        The screen streams straight from VMOS to your browser. Leaving this tab disconnects it —
                        the phone keeps running either way.
                    </p>
                </div>

                {{-- Identity tab --}}
                <div x-show="tab === 'identity'" x-cloak class="space-y-6">
                    <form method="POST" action="{{ route('instances.sim', $instance) }}" class="card p-6">
                        @csrf
                        <h3 class="text-base font-semibold text-ink-900">New phone number &amp; SIM</h3>
                        <p class="mt-1 text-sm text-ink-500">
                            Generates a fresh phone number, IMEI, IMSI and carrier for the country you pick.
                            The device restarts to apply it.
                        </p>
                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <label class="label" for="country_code">Country</label>
                                <select id="country_code" name="country_code" class="input" required>
                                    @foreach ($countries as $code => $name)
                                        <option value="{{ $code }}" @selected(($d['simCountry'] ?? null) === $code)>{{ $name }} ({{ $code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn-primary" onclick="return confirm('This restarts the device. Continue?')">
                                Generate new number
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('instances.gps', $instance) }}" class="card p-6">
                        @csrf
                        <h3 class="text-base font-semibold text-ink-900">GPS location</h3>
                        <p class="mt-1 text-sm text-ink-500">Set the coordinates apps see. Match these to your proxy's location for consistency.</p>
                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="label" for="latitude">Latitude</label>
                                <input id="latitude" name="latitude" type="number" step="any" class="input"
                                       value="{{ old('latitude', $d['latitude'] ?? '') }}" placeholder="1.3398" required>
                            </div>
                            <div>
                                <label class="label" for="longitude">Longitude</label>
                                <input id="longitude" name="longitude" type="number" step="any" class="input"
                                       value="{{ old('longitude', $d['longitude'] ?? '') }}" placeholder="103.6967" required>
                            </div>
                            <div class="flex items-end">
                                <button class="btn-primary w-full">Set location</button>
                            </div>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('instances.locale', $instance) }}" class="card p-6">
                        @csrf
                        <h3 class="text-base font-semibold text-ink-900">Language &amp; timezone</h3>
                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="label" for="timezone">Timezone</label>
                                <select id="timezone" name="timezone" class="input">
                                    <option value="">— unchanged —</option>
                                    @foreach (timezone_identifiers_list() as $tz)
                                        <option value="{{ $tz }}" @selected(($d['timeZone'] ?? null) === $tz)>{{ $tz }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="language">Language</label>
                                <select id="language" name="language" class="input">
                                    <option value="">— unchanged —</option>
                                    @foreach (['en' => 'English', 'es' => 'Spanish', 'pt' => 'Portuguese', 'fr' => 'French', 'de' => 'German', 'ar' => 'Arabic', 'hi' => 'Hindi', 'id' => 'Indonesian', 'zh' => 'Chinese', 'ru' => 'Russian', 'tr' => 'Turkish', 'vi' => 'Vietnamese'] as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button class="btn-primary w-full">Apply</button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Proxy tab --}}
                <div x-show="tab === 'proxy'" x-cloak class="space-y-6">
                    <form method="POST" action="{{ route('instances.proxy', $instance) }}" class="card p-6" x-data>
                        @csrf
                        <h3 class="text-base font-semibold text-ink-900">Set a proxy</h3>
                        <p class="mt-1 text-sm text-ink-500">
                            Route this device's traffic through your own proxy, so it appears to browse from that location.
                        </p>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="label" for="ip">Host / IP</label>
                                <input id="ip" name="ip" class="input" placeholder="154.81.40.200" value="{{ old('ip') }}" required>
                            </div>
                            <div>
                                <label class="label" for="port">Port</label>
                                <input id="port" name="port" type="number" class="input" placeholder="63007" value="{{ old('port') }}" required>
                            </div>
                            <div>
                                <label class="label" for="account">Username</label>
                                <input id="account" name="account" class="input" autocomplete="off" value="{{ old('account') }}">
                            </div>
                            <div>
                                <label class="label" for="password">Password</label>
                                <input id="password" name="password" type="password" class="input" autocomplete="off">
                            </div>
                            <div>
                                <label class="label" for="proxy_name">Protocol</label>
                                <select id="proxy_name" name="proxy_name" class="input">
                                    <option value="socks5">SOCKS5</option>
                                    <option value="http-relay">HTTP / HTTPS</option>
                                </select>
                            </div>
                            <div>
                                <label class="label" for="proxy_type">Mode</label>
                                <select id="proxy_type" name="proxy_type" class="input">
                                    <option value="proxy">Proxy</option>
                                    <option value="vpn">VPN (all traffic)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-ink-100 pt-5">
                            <button type="submit" formaction="{{ route('instances.proxy.test', $instance) }}" class="btn-secondary">
                                Test first
                            </button>
                            <button class="btn-primary">Apply proxy</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('instances.proxy.clear', $instance) }}"
                          class="card flex flex-wrap items-center justify-between gap-4 p-5">
                        @csrf @method('DELETE')
                        <div>
                            <p class="text-sm font-semibold text-ink-900">Remove proxy</p>
                            <p class="text-xs text-ink-500">Returns the device to its default data centre connection.</p>
                        </div>
                        <button class="btn-secondary">Disable proxy</button>
                    </form>
                </div>

                {{-- Apps tab --}}
                <div x-show="tab === 'apps'" x-cloak class="space-y-6">
                    <form method="POST" action="{{ route('instances.apps.install', $instance) }}" class="card p-6">
                        @csrf
                        <h3 class="text-base font-semibold text-ink-900">Install an app</h3>
                        <p class="mt-1 text-sm text-ink-500">Paste a direct link to an APK — it's downloaded onto the device and installed.</p>
                        <div class="mt-5 space-y-3">
                            <div>
                                <label class="label" for="apk_url">APK URL</label>
                                <input id="apk_url" name="apk_url" type="url" class="input" placeholder="https://example.com/app.apk" required>
                            </div>
                            <div>
                                <label class="label" for="package_name">Package name (optional)</label>
                                <input id="package_name" name="package_name" class="input font-mono text-sm" placeholder="com.zhiliaoapp.musically">
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end border-t border-ink-100 pt-5">
                            <button class="btn-primary">Download &amp; install</button>
                        </div>
                    </form>

                    <div class="card">
                        <div class="flex items-center justify-between px-5 py-4">
                            <h3 class="text-base font-semibold text-ink-900">Installed apps</h3>
                            <form method="POST" action="{{ route('instances.apps.refresh', $instance) }}">
                                @csrf
                                <button class="btn-ghost btn-sm">Refresh</button>
                            </form>
                        </div>

                        @php $appList = collect($apps ?? [])->flatMap(fn ($e) => $e['apps'] ?? $e['appList'] ?? (is_array($e) && isset($e['packageName']) ? [$e] : [])); @endphp

                        @if ($appList->isEmpty())
                            <p class="border-t border-ink-100 px-5 py-8 text-center text-sm text-ink-500">
                                No apps reported. If you just installed one, hit Refresh in a moment.
                            </p>
                        @else
                            <ul class="divide-y divide-ink-100 border-t border-ink-100">
                                @foreach ($appList as $app)
                                    @php $pkg = $app['packageName'] ?? $app['pkgName'] ?? null; @endphp
                                    @continue(! $pkg)
                                    <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-ink-900">{{ $app['appName'] ?? $app['name'] ?? $pkg }}</p>
                                            <p class="truncate font-mono text-xs text-ink-500">{{ $pkg }}</p>
                                        </div>
                                        <div class="flex gap-1.5">
                                            @foreach (['start' => 'Start', 'stop' => 'Stop', 'uninstall' => 'Uninstall'] as $action => $label)
                                                <form method="POST" action="{{ route('instances.apps.action', $instance) }}"
                                                      @if ($action === 'uninstall') onsubmit="return confirm('Uninstall {{ $pkg }}?')" @endif>
                                                    @csrf
                                                    <input type="hidden" name="package_name" value="{{ $pkg }}">
                                                    <input type="hidden" name="action" value="{{ $action }}">
                                                    <button class="{{ $action === 'uninstall' ? 'btn-ghost text-red-600 hover:bg-red-50' : 'btn-secondary' }} btn-sm">{{ $label }}</button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                {{-- Cloud Drive tab --}}
                <div x-show="tab === 'drive'" x-cloak class="space-y-6">
                    <div class="rounded-xl bg-ink-50 p-4 text-xs text-ink-500 ring-1 ring-inset ring-ink-200">
                        VMOS doesn't publish full field names for Cloud Drive the way it does for the phone-plan
                        endpoints — if capacity or a file looks wrong here, check <a href="{{ route('admin.diagnostics.index', ['probe' => 'storage_goods']) }}" class="font-medium text-brand-600 hover:underline">API diagnostics</a>
                        against the raw response.
                    </div>

                    @php
                        $used = $storage['used'] ?? $storage['usedSize'] ?? $storage['useSize'] ?? null;
                        $total = $storage['total'] ?? $storage['totalSize'] ?? $storage['capacity'] ?? null;
                    @endphp

                    <div class="card p-6">
                        <h3 class="text-base font-semibold text-ink-900">Storage</h3>
                        @if ($used !== null && $total !== null)
                            <p class="mt-2 text-sm text-ink-600">{{ $used }} / {{ $total }} used</p>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-ink-100">
                                <div class="h-full bg-brand-600" style="width: {{ $total > 0 ? min(100, round($used / $total * 100)) : 0 }}%"></div>
                            </div>
                        @else
                            <p class="mt-2 text-sm text-ink-500">Capacity not available right now.</p>
                        @endif

                        @if (auth()->user()->is_admin && ! empty($storageGoods))
                            <form method="POST" action="{{ route('instances.drive.buy-storage', $instance) }}"
                                  class="mt-5 flex flex-wrap items-end gap-3 border-t border-ink-100 pt-5"
                                  onsubmit="return confirm('This charges your VMOS balance. Continue?')">
                                @csrf
                                <div class="flex-1">
                                    <label class="label" for="good_id">Buy more storage (admin)</label>
                                    <select id="good_id" name="good_id" class="input">
                                        @foreach ($storageGoods as $good)
                                            <option value="{{ $good['id'] ?? $good['goodId'] ?? '' }}">
                                                {{ $good['name'] ?? $good['goodName'] ?? 'Storage package' }}
                                                @if (isset($good['price'])) — ${{ number_format($good['price'] / 100, 2) }} @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <input type="number" name="num" value="1" min="1" max="20" class="input w-20">
                                <button class="btn-secondary">Buy</button>
                            </form>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('instances.drive.upload', $instance) }}" class="card p-6">
                        @csrf
                        <h3 class="text-base font-semibold text-ink-900">Upload a file</h3>
                        <p class="mt-1 text-sm text-ink-500">Paste a direct link — it's downloaded straight onto the device's cloud disk.</p>
                        <div class="mt-5 space-y-3">
                            <div>
                                <label class="label" for="drive_url">File URL</label>
                                <input id="drive_url" name="url" type="url" class="input" placeholder="https://example.com/file.pdf" required>
                            </div>
                            <div>
                                <label class="label" for="file_name">File name (optional)</label>
                                <input id="file_name" name="file_name" class="input" placeholder="document.pdf">
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end border-t border-ink-100 pt-5">
                            <button class="btn-primary">Upload</button>
                        </div>
                    </form>

                    <div class="card">
                        <h3 class="px-5 py-4 text-base font-semibold text-ink-900">Files</h3>
                        @php $fileList = collect($files ?? [])->flatMap(fn ($e) => $e['records'] ?? $e['files'] ?? (is_array($e) && (isset($e['fileId']) || isset($e['id'])) ? [$e] : [])); @endphp

                        @if ($fileList->isEmpty())
                            <p class="border-t border-ink-100 px-5 py-8 text-center text-sm text-ink-500">No files yet.</p>
                        @else
                            <ul class="divide-y divide-ink-100 border-t border-ink-100">
                                @foreach ($fileList as $file)
                                    @php $fileId = $file['fileId'] ?? $file['id'] ?? null; @endphp
                                    @continue(! $fileId)
                                    <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-ink-900">{{ $file['fileName'] ?? $file['name'] ?? $fileId }}</p>
                                            @if (isset($file['size']))
                                                <p class="text-xs text-ink-500">{{ $file['size'] }}</p>
                                            @endif
                                        </div>
                                        <form method="POST" action="{{ route('instances.drive.delete', $instance) }}"
                                              onsubmit="return confirm('Delete this file?')">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="file_ids[]" value="{{ $fileId }}">
                                            <button class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Delete</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="card p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-ink-900">Backups</h3>
                            <form method="POST" action="{{ route('instances.drive.backup', $instance) }}">
                                @csrf
                                <button class="btn-secondary btn-sm">Back up now</button>
                            </form>
                        </div>

                        @php $backupTasks = $instance->tasks->where('type', 'backup')->take(5); @endphp
                        @if ($backupTasks->isEmpty())
                            <p class="mt-3 text-sm text-ink-500">No backups yet.</p>
                        @else
                            <ul class="mt-4 space-y-2">
                                @foreach ($backupTasks as $task)
                                    <li class="flex items-center justify-between rounded-lg bg-ink-50 p-3 text-sm">
                                        <span class="text-ink-600">
                                            {{ $task->created_at->format('d M H:i') }}
                                            @if ($progress = $task->result['progress'] ?? null)
                                                — {{ is_array($progress) ? json_encode($progress) : $progress }}
                                            @endif
                                        </span>
                                        <form method="POST" action="{{ route('instances.drive.backup-progress', [$instance, $task]) }}">
                                            @csrf
                                            <button class="btn-ghost btn-sm">Check progress</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                {{-- Advanced tab --}}
                <div x-show="tab === 'advanced'" x-cloak class="space-y-6">
                    <form method="POST" action="{{ route('instances.new-device', $instance) }}" class="card p-6"
                          onsubmit="return confirm('This ERASES the device and gives it a brand new identity. This cannot be undone. Continue?')">
                        @csrf
                        <h3 class="text-base font-semibold text-ink-900">One-key new device</h3>
                        <p class="mt-1 text-sm text-ink-500">
                            Wipes everything and generates a completely new hardware identity — new IMEI, MAC, phone number,
                            and matching GPS/timezone. Use this to start fresh with no trace of the previous account.
                        </p>
                        <label class="mt-4 flex items-center gap-2.5 text-sm text-ink-700">
                            <input type="checkbox" name="wipe_data" value="1" checked
                                   class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                            Erase all data (recommended)
                        </label>
                        <div class="mt-6 flex justify-end border-t border-ink-100 pt-5">
                            <button class="btn-danger">Reset to a new device</button>
                        </div>
                    </form>

                    <div class="card p-6">
                        <h3 class="text-base font-semibold text-ink-900">ADB access</h3>
                        <p class="mt-1 text-sm text-ink-500">
                            Enable Android Debug Bridge to drive this device from your computer with scripts or automation tools.
                        </p>
                        <div class="mt-5 flex gap-2">
                            <form method="POST" action="{{ route('instances.adb', $instance) }}">
                                @csrf
                                <input type="hidden" name="enable" value="1">
                                <button class="btn-primary">Enable ADB</button>
                            </form>
                            <form method="POST" action="{{ route('instances.adb', $instance) }}">
                                @csrf
                                <input type="hidden" name="enable" value="0">
                                <button class="btn-secondary">Disable</button>
                            </form>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('instances.reset', $instance) }}" class="card border-l-4 border-l-red-400 p-6"
                          onsubmit="return confirm('Factory reset erases ALL data on this device. Continue?')">
                        @csrf
                        <h3 class="text-base font-semibold text-ink-900">Factory reset</h3>
                        <p class="mt-1 text-sm text-ink-500">Clears all data but keeps the same hardware identity.</p>
                        <div class="mt-5 flex justify-end">
                            <button class="btn-danger btn-sm">Factory reset</button>
                        </div>
                    </form>
                </div>

                {{-- Recent tasks --}}
                @if ($instance->tasks->isNotEmpty())
                    <div class="card">
                        <h3 class="px-5 py-4 text-base font-semibold text-ink-900">Recent actions</h3>
                        <div class="table-wrap border-t border-ink-100">
                            <table class="table">
                                <thead><tr><th>Action</th><th>Status</th><th>When</th></tr></thead>
                                <tbody>
                                    @foreach ($instance->tasks->sortByDesc('id')->take(8) as $task)
                                        <tr>
                                            <td class="text-sm">{{ str($task->type)->headline() }}</td>
                                            <td>
                                                <span class="{{ match ($task->status) {
                                                    'completed' => 'badge-green',
                                                    'failed' => 'badge-red',
                                                    default => 'badge-amber',
                                                } }}">{{ str($task->status)->headline() }}</span>
                                            </td>
                                            <td class="text-sm text-ink-500">{{ $task->created_at->diffForHumans() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endunless
</x-app-layout>
