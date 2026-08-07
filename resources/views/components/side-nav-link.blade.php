@props(['active' => false, 'icon' => null, 'badge' => null])

<a {{ $attributes->merge(['class' => 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors '
    . ($active
        ? 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-100'
        : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900')]) }}>
    @if ($icon)
        <svg class="h-5 w-5 flex-none {{ $active ? 'text-brand-600' : 'text-ink-400' }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
        </svg>
    @endif
    <span class="flex-1">{{ $slot }}</span>
    @if ($badge)
        <span class="rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">{{ $badge }}</span>
    @endif
</a>
