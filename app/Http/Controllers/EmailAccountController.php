<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Models\Sku;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\Auth;
use Throwable;

class EmailAccountController extends Controller
{
    /** Storefront: browse purchasable email account types. */
    public function index()
    {
        $skus = Sku::available()->emailAccounts()->where('price', '>', 0)->orderBy('sort_order')->orderBy('name')->get();

        $owned = Auth::check()
            ? EmailAccount::where('user_id', Auth::id())->with('sku')->latest()->get()
            : collect();

        return view('email-accounts.index', compact('skus', 'owned'));
    }

    /** Polls VMOS for a fresh verification code on an already-delivered account. */
    public function refresh(EmailAccount $emailAccount, VmosCloudPhoneService $vmos)
    {
        abort_unless($emailAccount->user_id === Auth::id(), 403);

        try {
            $response = $vmos->emailOrderList($emailAccount->vmos_order_id);
            $entries = $response['data'] ?? [];

            $match = collect($entries)->first(function ($entry) use ($emailAccount) {
                $entryEmail = $entry['email'] ?? $entry['emailAddress'] ?? $entry['account'] ?? null;

                return $entryEmail === $emailAccount->email;
            }) ?? ($entries[0] ?? null);

            if ($match) {
                $code = $match['code'] ?? $match['verifyCode'] ?? $match['verificationCode'] ?? null;

                $emailAccount->update([
                    'latest_code' => $code ?? $emailAccount->latest_code,
                    'raw_payload' => $match,
                    'code_fetched_at' => now(),
                ]);

                return back()->with('status', $code ? "New code: {$code}" : 'No verification code from VMOS yet — try again in a moment.');
            }

            return back()->with('error', 'VMOS did not return this email account. It may have expired.');
        } catch (Throwable $e) {
            return back()->with('error', 'Could not reach VMOS: '.$e->getMessage());
        }
    }
}
