<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * A 6-digit code emailed at registration to confirm the address is real
 * before the account can be used. The Breeze scaffolding this app shipped
 * with (signed verification link, MustVerifyEmail) was never actually wired
 * up — User doesn't implement MustVerifyEmail and nothing required
 * 'verified' — so until this, anyone could register with a fake address and
 * use the site immediately.
 */
class EmailOtpService
{
    protected const EXPIRY_MINUTES = 10;

    public function generateAndSend(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'email_otp' => Hash::make($code),
            'email_otp_expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ])->save();

        Mail::raw(
            "Your verification code is {$code}. It expires in ".self::EXPIRY_MINUTES.' minutes.',
            fn ($message) => $message->to($user->email)->subject(config('app.name').' verification code'),
        );
    }

    public function verify(User $user, string $code): bool
    {
        if (! $user->email_otp || ! $user->email_otp_expires_at || $user->email_otp_expires_at->isPast()) {
            return false;
        }

        if (! Hash::check($code, $user->email_otp)) {
            return false;
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_otp' => null,
            'email_otp_expires_at' => null,
        ])->save();

        return true;
    }
}
