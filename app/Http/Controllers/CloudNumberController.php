<?php

namespace App\Http\Controllers;

use App\Models\CloudInstance;
use App\Models\CloudNumber;
use App\Models\Sku;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CloudNumberController extends Controller
{
    /** Storefront: browse purchasable cloud number plans. */
    public function index()
    {
        $skus = Sku::available()->cloudNumbers()->where('price', '>', 0)->orderBy('default_country_code')->orderBy('duration_minutes')->get();

        $owned = Auth::check()
            ? CloudNumber::where('user_id', Auth::id())->with('sku')->latest()->get()
            : collect();

        $devices = Auth::check()
            ? CloudInstance::where('user_id', Auth::id())->whereNotNull('pad_code')->get(['id', 'pad_code', 'nickname'])
            : collect();

        return view('cloud-numbers.index', compact('skus', 'owned', 'devices'));
    }

    /** Binds an owned, unbound number to one of the customer's devices — this restarts the device. */
    public function bind(Request $request, CloudNumber $cloudNumber)
    {
        abort_unless($cloudNumber->user_id === Auth::id(), 403);

        $data = $request->validate([
            'pad_code' => ['required', 'string'],
            'restart_acknowledged' => ['accepted'],
        ]);

        $owns = CloudInstance::where('user_id', Auth::id())->where('pad_code', $data['pad_code'])->exists();
        abort_unless($owns, 403);

        if (! $cloudNumber->vmos_number_id) {
            return back()->with('error', 'This number isn\'t ready to bind yet — try again in a moment.');
        }

        try {
            $vmos = app(VmosCloudPhoneService::class);
            $requestId = 'bind-'.$cloudNumber->id.'-'.Str::random(8);

            $vmos->bindCloudNumber($cloudNumber->vmos_number_id, $data['pad_code'], $requestId, true);

            $cloudNumber->update([
                'bound_pad_code' => $data['pad_code'],
                'bind_state' => 'RUNNING',
                'bind_request_id' => $requestId,
            ]);

            return back()->with('status', 'Binding started — the device will restart shortly. Check back in a minute.');
        } catch (Throwable $e) {
            Log::warning('cloud_numbers.bind_failed', ['cloud_number_id' => $cloudNumber->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not start binding. Please try again.');
        }
    }

    /** Refreshes the bind task's progress. */
    public function bindStatus(CloudNumber $cloudNumber)
    {
        abort_unless($cloudNumber->user_id === Auth::id(), 403);

        if (! $cloudNumber->vmos_number_id) {
            return back()->with('error', 'This number isn\'t ready yet.');
        }

        try {
            $vmos = app(VmosCloudPhoneService::class);
            $data = $vmos->cloudNumberBindStatus($cloudNumber->vmos_number_id)['data'] ?? [];

            $cloudNumber->update(['bind_state' => $data['state'] ?? $cloudNumber->bind_state]);

            return back()->with('status', 'Bind status refreshed.');
        } catch (Throwable $e) {
            Log::warning('cloud_numbers.bind_status_failed', ['cloud_number_id' => $cloudNumber->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not check binding status right now.');
        }
    }

    public function toggleAutoRenew(Request $request, CloudNumber $cloudNumber)
    {
        abort_unless($cloudNumber->user_id === Auth::id(), 403);

        if (! $cloudNumber->vmos_number_id) {
            return back()->with('error', 'This number isn\'t ready yet.');
        }

        try {
            $vmos = app(VmosCloudPhoneService::class);
            $newValue = ! $cloudNumber->auto_renew;

            $response = $vmos->cloudNumberSetAutoRenew($cloudNumber->vmos_number_id, $newValue, (string) $cloudNumber->row_version);

            $cloudNumber->update([
                'auto_renew' => $newValue,
                'row_version' => $response['data']['item']['rowVersion'] ?? $cloudNumber->row_version,
            ]);

            return back()->with('status', $newValue ? 'Auto-renew turned on.' : 'Auto-renew turned off.');
        } catch (Throwable $e) {
            Log::warning('cloud_numbers.auto_renew_failed', ['cloud_number_id' => $cloudNumber->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not update auto-renew — try refreshing the page and again.');
        }
    }

    public function release(CloudNumber $cloudNumber)
    {
        abort_unless($cloudNumber->user_id === Auth::id(), 403);

        try {
            $vmos = app(VmosCloudPhoneService::class);
            $vmos->releaseCloudNumber((string) $cloudNumber->number);

            $cloudNumber->update(['vmos_status' => 'disabled']);

            return back()->with('status', 'Number released. No refund for the current billing period.');
        } catch (Throwable $e) {
            Log::warning('cloud_numbers.release_failed', ['cloud_number_id' => $cloudNumber->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not release this number right now. Please try again.');
        }
    }

    /** Pulls the latest SMS received on this number. */
    public function refreshSms(CloudNumber $cloudNumber)
    {
        abort_unless($cloudNumber->user_id === Auth::id(), 403);

        try {
            $vmos = app(VmosCloudPhoneService::class);
            $records = $vmos->cloudNumberSms((string) $cloudNumber->number)['data']['records'] ?? [];

            $cloudNumber->update(['raw_payload' => array_merge($cloudNumber->raw_payload ?? [], ['sms' => $records])]);

            return back()->with('status', count($records).' message(s) loaded.');
        } catch (Throwable $e) {
            Log::warning('cloud_numbers.sms_refresh_failed', ['cloud_number_id' => $cloudNumber->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not reach the provisioning service right now. Please try again.');
        }
    }
}
