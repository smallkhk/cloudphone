@php
    $layout = auth()->check() ? 'app' : 'marketing';
    $grouped = $skus->groupBy('name');
@endphp

@if ($layout === 'marketing')
<x-marketing-layout>
    <div class="bg-ink-950 pb-20 pt-32">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">Choose your cloud phone</h1>
            <p class="mt-5 text-lg text-ink-300">
                Every plan is a dedicated Android device with its own identity. Pay in USDT — no subscription lock-in.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        @include('plans.partials.grid')
    </div>
</x-marketing-layout>
@else
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Browse plans" subtitle="Pick a device and duration. Your cloud phone is provisioned as soon as payment confirms." />
    </x-slot>

    @include('plans.partials.grid')
</x-app-layout>
@endif
