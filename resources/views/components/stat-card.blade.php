@props(['label', 'value', 'sub' => null, 'icon' => null, 'tone' => 'brand'])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600 ring-brand-100',
        'green' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
        'amber' => 'bg-amber-50 text-amber-600 ring-amber-100',
        'red' => 'bg-red-50 text-red-600 ring-red-100',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'card p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-ink-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-extrabold tracking-tight text-ink-900">{{ $value }}</p>
            @if ($sub)
                <p class="mt-1 text-xs text-ink-500">{{ $sub }}</p>
            @endif
        </div>
        @if ($icon)
            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl ring-1 ring-inset {{ $tones[$tone] ?? $tones['brand'] }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </span>
        @endif
    </div>
</div>
