@php $layout = auth()->check() ? 'app' : 'marketing'; @endphp

@if ($layout === 'marketing')
<x-marketing-layout>
    <div class="bg-ink-950 pb-20 pt-32">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">Proxies</h1>
            <p class="mt-5 text-lg text-ink-300">
                A residential proxy of your own, not tied to any one device — attach it to whichever cloud phone you like, whenever you like.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        @include('proxies.partials.grid')
    </div>
</x-marketing-layout>
@else
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Proxies" subtitle="Buy a residential proxy on its own, then attach it to any of your cloud phones." />
    </x-slot>

    @include('proxies.partials.grid')
</x-app-layout>
@endif
