<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\Auth\EmailOtpService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

/**
 * Sign-in for the iPhone app. Same rules as the website: an account isn't
 * usable until its email is confirmed with the 6-digit code, and signing in
 * with an unconfirmed email sends a fresh code instead of a token.
 */
class AuthController extends Controller
{
    /** Wrong codes allowed per issued code before it's thrown away. */
    protected const MAX_OTP_ATTEMPTS = 5;

    public function register(Request $request, EmailOtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));
        $otp->generateAndSend($user);

        return response()->json(['otp_required' => true, 'email' => $user->email]);
    }

    public function login(Request $request, EmailOtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Those details don\'t match our records.'], 422);
        }

        if (! $user->email_verified_at) {
            $otp->generateAndSend($user);

            return response()->json(['otp_required' => true, 'email' => $user->email]);
        }

        return $this->signedIn($user, $data['device_name'] ?? null);
    }

    public function verifyOtp(Request $request, EmailOtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', strtolower($data['email']))->whereNull('email_verified_at')->first();

        if (! $user) {
            return response()->json(['message' => 'That code is incorrect or has expired.'], 422);
        }

        // The code is only 6 digits, so cap wrong guesses per issued code;
        // after that a new one has to be requested.
        $failKey = "mobile-otp-fails:{$user->id}";

        if ((int) Cache::get($failKey, 0) >= self::MAX_OTP_ATTEMPTS) {
            $user->forceFill(['email_otp' => null, 'email_otp_expires_at' => null])->save();
            Cache::forget($failKey);

            return response()->json(['message' => 'Too many wrong codes. Tap "Resend code" for a new one.'], 422);
        }

        if (! $otp->verify($user, trim($data['code']))) {
            Cache::put($failKey, (int) Cache::get($failKey, 0) + 1, now()->addMinutes(15));

            return response()->json(['message' => 'That code is incorrect or has expired.'], 422);
        }

        Cache::forget($failKey);

        return $this->signedIn($user->fresh(), $data['device_name'] ?? null);
    }

    public function resendOtp(Request $request, EmailOtpService $otp): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::where('email', strtolower($data['email']))->whereNull('email_verified_at')->first();

        if ($user) {
            $otp->generateAndSend($user);
            Cache::forget("mobile-otp-fails:{$user->id}");
        }

        // Same answer either way — doesn't reveal which emails have accounts.
        return response()->json(['message' => 'If that account is waiting for a code, a new one has been sent.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    protected function signedIn(User $user, ?string $deviceName): JsonResponse
    {
        return response()->json([
            'token' => ApiToken::issue($user, $deviceName ?? 'iPhone'),
            'user' => MobileResources::user($user),
        ]);
    }
}
