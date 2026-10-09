<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\EmailOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, EmailOtpService $otp): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Credentials are correct, but the email was never confirmed (they
        // abandoned the code screen after registering, most likely) — the
        // account isn't usable until that happens, same as right after
        // signup. Without this, OTP would only gate the registration form
        // itself, not actual access to the account.
        if (! $user->email_verified_at) {
            Auth::logout();

            $otp->generateAndSend($user);
            $request->session()->regenerate();
            $request->session()->put('otp_user_id', $user->id);

            return redirect()->route('verification.otp');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
