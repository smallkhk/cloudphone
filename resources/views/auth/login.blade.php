<x-guest-layout>
    <h1 class="text-2xl font-extrabold tracking-tight text-ink-900">Welcome back</h1>
    <p class="mt-2 text-sm text-ink-500">Log in to manage your cloud phones.</p>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" class="mb-0" />
                @if (Route::has('password.request'))
                    <a class="mb-1.5 text-xs font-medium text-brand-600 hover:text-brand-700" href="{{ route('password.request') }}">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex items-center gap-2.5">
            <input id="remember_me" type="checkbox" name="remember"
                   class="rounded border-ink-300 text-brand-600 shadow-sm focus:ring-brand-500">
            <span class="text-sm text-ink-600">{{ __('Keep me logged in') }}</span>
        </label>

        <x-primary-button class="w-full">{{ __('Log in') }}</x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-500">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:text-brand-700">Create one</a>
    </p>
</x-guest-layout>
