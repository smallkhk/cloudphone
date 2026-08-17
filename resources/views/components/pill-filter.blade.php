@props(['label', 'param', 'options', 'active', 'params' => [], 'route', 'allLabel' => 'All'])

<div class="mb-3 flex flex-wrap items-center gap-2">
    <span class="pr-1 text-xs font-semibold uppercase tracking-wider text-ink-400">{{ $label }}</span>
    <a href="{{ route($route, array_filter($params)) }}"
       class="rounded-full px-3 py-1.5 text-xs font-medium {{ ! $active ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-600 hover:bg-ink-200' }}">
        {{ $allLabel }}
    </a>
    @foreach ($options as $value => $optionLabel)
        <a href="{{ route($route, array_filter(array_merge($params, [$param => $value]))) }}"
           class="rounded-full px-3 py-1.5 text-xs font-medium {{ (string) $active === (string) $value ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-600 hover:bg-ink-200' }}">
            {{ $optionLabel }}
        </a>
    @endforeach
</div>
