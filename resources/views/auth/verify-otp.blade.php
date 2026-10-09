<x-guest-layout>
    <h1 class="text-2xl font-extrabold tracking-tight text-ink-900">Check your email</h1>
    <p class="mt-2 text-sm text-ink-500">
        We sent a 6-digit code to <span class="font-medium text-ink-700">{{ $email }}</span>.
        Enter it below to finish creating your account.
    </p>

    @if (session('status'))
        <p class="mt-4 text-sm font-medium text-emerald-600">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('verification.otp.verify') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <x-input-label for="code" value="Verification code" />
            <x-text-input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                          autocomplete="one-time-code" autofocus required
                          class="text-center text-lg tracking-[0.3em]" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">Verify &amp; continue</x-primary-button>
    </form>

    <form method="POST" action="{{ route('verification.otp.resend') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm font-semibold text-brand-600 hover:text-brand-700">
            Didn't get a code? Resend
        </button>
    </form>
</x-guest-layout>
