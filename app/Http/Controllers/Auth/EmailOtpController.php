<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\EmailOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmailOtpController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register');
        }

        return view('auth.verify-otp', ['email' => $user->email]);
    }

    public function store(Request $request, EmailOtpService $otp): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register');
        }

        $request->validate(['code' => ['required', 'string']]);

        if (! $otp->verify($user, trim($request->string('code')->value()))) {
            return back()->withErrors(['code' => 'That code is incorrect or has expired.']);
        }

        $request->session()->forget('otp_user_id');
        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    public function resend(Request $request, EmailOtpService $otp): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register');
        }

        $otp->generateAndSend($user);

        return back()->with('status', 'A new code has been sent.');
    }

    /** The just-registered, not-yet-verified account this session is confirming — never an already-verified one. */
    protected function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('otp_user_id');

        return $id ? User::whereNull('email_verified_at')->find($id) : null;
    }
}
