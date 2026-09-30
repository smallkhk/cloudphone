@php $layout = auth()->check() ? 'app' : 'marketing'; @endphp

@if ($layout === 'marketing')
<x-marketing-layout>
    <div class="bg-ink-950 pb-20 pt-32">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">Cloud numbers</h1>
            <p class="mt-5 text-lg text-ink-300">
                A rented number you keep, with SMS delivered straight to one of your cloud phones.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        @include('cloud-numbers.partials.grid')
    </div>
</x-marketing-layout>
@else
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Cloud numbers" subtitle="A rented number on a 30/90/365-day plan, bound to one of your cloud phones to receive SMS." />
    </x-slot>

    @include('cloud-numbers.partials.grid')
</x-app-layout>
@endif
