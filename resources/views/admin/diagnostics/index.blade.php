<x-admin-layout>
    <x-slot name="header">
        <x-page-header title="API diagnostics"
                       subtitle="See exactly what VMOS returns. Use this when a sync comes back empty." />
    </x-slot>

    @unless ($configured)
        <div class="card mb-6 border-l-4 border-l-amber-400 p-5">
            <p class="text-sm text-ink-700">Add your VMOS Access Key and Secret Key first.</p>
            <a href="{{ route('admin.settings.edit', 'vmos') }}" class="btn-primary btn-sm mt-3">Go to VMOS settings</a>
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-4">
        <nav class="lg:col-span-1">
            <div class="card overflow-hidden p-2">
                @foreach ($probes as $key => [$method, $path, $params, $label])
                    <a href="{{ route('admin.diagnostics.index', ['probe' => $key]) }}"
                       class="block rounded-xl px-3 py-2.5 text-sm font-medium transition-colors {{ $probe === $key ? 'bg-brand-50 text-brand-700' : 'text-ink-600 hover:bg-ink-100' }}">
                        <span class="block">{{ $label }}</span>
                        <span class="mt-0.5 block font-mono text-[10px] text-ink-400">{{ $method }} {{ \Illuminate\Support\Str::afterLast($path, '/') }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

        <div class="lg:col-span-3">
            @unless ($probe)
                <div class="card p-8 text-center">
                    <p class="text-sm text-ink-600">Pick a request on the left to see the raw response.</p>
                    <p class="mx-auto mt-3 max-w-md text-xs text-ink-500">
                        If <strong>Plan catalogue</strong> returns an empty <code>configs</code> array, your VMOS account
                        has no purchasable packages for that Android version — that's why syncing finds nothing.
                        Try the “no version filter” option, and check the price values to confirm whether they're in
                        cents or whole dollars.
                    </p>
                </div>
            @else
                @php [$method, $path, $params, $label] = $probes[$probe]; @endphp

                <div class="card overflow-hidden">
                    <div class="border-b border-ink-100 px-5 py-4">
                        <h2 class="text-base font-semibold text-ink-900">{{ $label }}</h2>
                        <p class="mt-1 break-all font-mono text-xs text-ink-500">{{ $method }} {{ $path }}
                            @if ($params) &nbsp;{{ json_encode($params) }} @endif
                        </p>
                    </div>

                    @if ($error)
                        <div class="p-5">
                            <div class="rounded-xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-inset ring-red-600/20">
                                <p class="font-semibold">Request failed</p>
                                <p class="mt-1 break-words">{{ $error }}</p>
                            </div>
                        </div>
                    @else
                        @php
                            $configs = $result['data']['configs'] ?? null;
                            $pads = ($probe === 'pads') ? ($result['data'] ?? []) : null;
                        @endphp

                        {{-- Plain-language summary above the raw JSON --}}
                        @if (is_array($configs))
                            <div class="border-b border-ink-100 bg-ink-50 p-5">
                                @if (count($configs) === 0)
                                    <p class="text-sm font-medium text-amber-800">
                                        VMOS returned <strong>0 device configurations</strong> — nothing to sync.
                                    </p>
                                    <p class="mt-1 text-xs text-ink-600">
                                        Your account may not have this Android version enabled, or no packages are published to it.
                                        Try another probe, or check the VMOS console's own pricing page.
                                    </p>
                                @else
                                    <p class="text-sm font-medium text-emerald-800">
                                        Found <strong>{{ count($configs) }}</strong> device configuration(s),
                                        <strong>{{ collect($configs)->sum(fn ($c) => count($c['goodTimes'] ?? [])) }}</strong> purchasable plan(s).
                                    </p>
                                    <div class="table-wrap mt-4">
                                        <table class="table">
                                            <thead><tr><th>Device</th><th>Duration</th><th>Raw price</th><th>As cents</th><th>goodId</th></tr></thead>
                                            <tbody>
                                                @foreach (collect($configs)->take(6) as $c)
                                                    @foreach (collect($c['goodTimes'] ?? [])->take(3) as $g)
                                                        <tr>
                                                            <td class="text-sm">{{ $c['configName'] ?? '?' }}</td>
                                                            <td class="text-sm text-ink-600">{{ $g['showContent'] ?? '?' }}</td>
                                                            <td class="font-mono text-sm">{{ $g['currentPrice'] ?? '?' }}</td>
                                                            <td class="text-sm text-ink-600">${{ number_format(((float) ($g['currentPrice'] ?? 0)) / 100, 2) }}</td>
                                                            <td class="font-mono text-xs text-ink-500">{{ $g['id'] ?? '?' }}</td>
                                                        </tr>
                                                    @endforeach
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="mt-3 text-xs text-ink-500">
                                        Compare “As cents” against VMOS's real prices. If it matches, leave the price unit as
                                        <strong>cents</strong> in Settings → VMOS. If the raw price is already the dollar
                                        amount, switch it to <strong>whole units</strong>.
                                    </p>
                                @endif
                            </div>
                        @elseif (is_array($pads))
                            <div class="border-b border-ink-100 bg-ink-50 p-5">
                                <p class="text-sm font-medium {{ count($pads) ? 'text-emerald-800' : 'text-amber-800' }}">
                                    {{ count($pads) }} cloud phone(s) on this VMOS account.
                                </p>
                            </div>
                        @endif

                        <div class="overflow-x-auto bg-ink-950 p-5">
<pre class="whitespace-pre font-mono text-xs leading-relaxed text-ink-200">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                </div>
            @endunless
        </div>
    </div>
</x-admin-layout>
