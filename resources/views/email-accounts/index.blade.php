@php $layout = auth()->check() ? 'app' : 'marketing'; @endphp

@if ($layout === 'marketing')
<x-marketing-layout>
    <div class="bg-ink-950 pb-20 pt-32">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">Email verification accounts</h1>
            <p class="mt-5 text-lg text-ink-300">
                Pre-made email accounts for service registrations, delivered with the address and password —
                pull the verification code straight from here once you've triggered it.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        @include('email-accounts.partials.grid')
    </div>
</x-marketing-layout>
@else
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Email accounts" subtitle="Buy a pre-made email account, then pull its verification code once you're ready to use it." />
    </x-slot>

    @include('email-accounts.partials.grid')
</x-app-layout>
@endif
