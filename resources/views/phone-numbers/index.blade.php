@php $layout = auth()->check() ? 'app' : 'marketing'; @endphp

@if ($layout === 'marketing')
<x-marketing-layout>
    <div class="bg-ink-950 pb-20 pt-32">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">Phone verification numbers</h1>
            <p class="mt-5 text-lg text-ink-300">
                Temporary phone numbers for SMS verification codes on service registrations.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        @include('phone-numbers.partials.grid')
    </div>
</x-marketing-layout>
@else
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Phone numbers" subtitle="Buy a temporary phone number, then pull its SMS verification code once you're ready to use it." />
    </x-slot>

    @include('phone-numbers.partials.grid')
</x-app-layout>
@endif
