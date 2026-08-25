<?php

namespace App\Http\Controllers;

use App\Models\PhoneNumber;
use App\Models\Sku;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class PhoneNumberController extends Controller
{
    /** Storefront: browse purchasable phone-number types. */
    public function index()
    {
        $skus = Sku::available()->phoneNumbers()->where('price', '>', 0)->orderBy('sort_order')->orderBy('name')->get();

        $owned = Auth::check()
            ? PhoneNumber::where('user_id', Auth::id())->with('sku')->latest()->get()
            : collect();

        return view('phone-numbers.index', compact('skus', 'owned'));
    }

    /** Polls VMOS for a fresh SMS verification code on an already-delivered number. */
    public function refresh(PhoneNumber $phoneNumber, VmosCloudPhoneService $vmos)
    {
        abort_unless($phoneNumber->user_id === Auth::id(), 403);

        try {
            $response = $vmos->smsCode($phoneNumber->vmos_order_id);
            $data = $response['data'] ?? [];
            $code = $data['code'] ?? $data['verifyCode'] ?? $data['verificationCode'] ?? null;

            if ($code) {
                $phoneNumber->update([
                    'latest_code' => $code,
                    'raw_payload' => $data,
                    'code_fetched_at' => now(),
                ]);

                return back()->with('status', "New code: {$code}");
            }

            return back()->with('error', 'No verification code yet — try again in a moment.');
        } catch (Throwable $e) {
            Log::warning('phone_numbers.refresh_failed', ['phone_number_id' => $phoneNumber->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not reach the provisioning service right now. Please try again.');
        }
    }
}
