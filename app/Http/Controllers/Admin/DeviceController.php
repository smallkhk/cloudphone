<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\VmosApiException;
use App\Http\Controllers\Controller;
use App\Models\CloudInstance;
use App\Models\Sku;
use App\Models\User;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Admin-side view of every cloud phone on the VMOS account, including devices
 * bought outside the storefront, plus manual allocation to customers.
 */
class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('q')->trim()->value();
        $filter = $request->string('filter')->value();

        $instances = CloudInstance::query()
            ->with(['user', 'sku'])
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('pad_code', 'like', "%{$search}%")
                ->orWhere('equipment_id', 'like', "%{$search}%")
                ->orWhere('nickname', 'like', "%{$search}%")))
            ->when($filter === 'unallocated', fn ($q) => $q->whereNull('user_id'))
            ->when($filter === 'allocated', fn ($q) => $q->whereNotNull('user_id'))
            ->when($filter === 'online', fn ($q) => $q->where('online', true))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.devices.index', [
            'instances' => $instances,
            'search' => $search,
            'filter' => $filter,
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'stats' => [
                'total' => CloudInstance::count(),
                'allocated' => CloudInstance::whereNotNull('user_id')->count(),
                'unallocated' => CloudInstance::whereNull('user_id')->count(),
                'online' => CloudInstance::where('online', true)->count(),
            ],
        ]);
    }

    /** Assigns a device to a customer (or moves it between customers). */
    public function allocate(Request $request, CloudInstance $device)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'allocation_note' => ['nullable', 'string', 'max:500'],
            'nickname' => ['nullable', 'string', 'max:255'],
        ]);

        $device->update([
            'user_id' => $data['user_id'],
            'allocated_at' => now(),
            'allocated_by' => Auth::id(),
            'allocation_note' => $data['allocation_note'] ?? null,
            'nickname' => $data['nickname'] ?? $device->nickname,
        ]);

        $user = User::find($data['user_id']);

        return back()->with('status', "Allocated {$device->pad_code} to {$user->email}.");
    }

    /** Returns a device to unallocated stock. */
    public function deallocate(CloudInstance $device)
    {
        $device->update([
            'user_id' => null,
            'allocated_at' => null,
            'allocated_by' => null,
            'allocation_note' => null,
        ]);

        return back()->with('status', "{$device->pad_code} returned to stock.");
    }

    public function update(Request $request, CloudInstance $device)
    {
        $data = $request->validate([
            'nickname' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $device->update($data);

        return back()->with('status', 'Device updated.');
    }

    public function destroy(CloudInstance $device)
    {
        $code = $device->pad_code ?? "#{$device->id}";
        $device->delete();

        return back()->with('status', "Removed {$code} from this site. (The device itself still exists in your VMOS account.)");
    }

    /**
     * Pulls every cloud phone on the VMOS account into this site, creating local
     * records for devices bought directly in the VMOS console. Imported devices
     * land in unallocated stock, ready to hand to a customer.
     */
    public function import(VmosCloudPhoneService $vmos)
    {
        if (! filled(config('vmos.access_key'))) {
            return back()->with('error', 'Add your VMOS credentials under Settings → VMOS first.');
        }

        try {
            $response = $vmos->listPads();
        } catch (VmosApiException|Throwable $e) {
            return back()->with('error', 'Could not reach VMOS: '.$e->getMessage());
        }

        $imported = 0;
        $updated = 0;

        foreach ($response['data'] ?? [] as $pad) {
            if (empty($pad['padCode'])) {
                continue;
            }

            $instance = CloudInstance::firstOrNew(['pad_code' => $pad['padCode']]);
            $isNew = ! $instance->exists;

            $instance->fill([
                'equipment_id' => $pad['equipmentId'] ?? $instance->equipment_id,
                'pad_status' => $pad['cvmStatus'] ?? $instance->pad_status,
                'online' => (bool) ($pad['status'] ?? 0),
                'screenshot_url' => $pad['screenshotLink'] ?? $instance->screenshot_url,
                'nickname' => $instance->nickname ?? ($pad['padName'] ?? null),
                'expires_at' => isset($pad['signExpirationTimeTamp'])
                    ? \Carbon\Carbon::createFromTimestampMs($pad['signExpirationTimeTamp'])
                    : $instance->expires_at,
                'last_synced_at' => now(),
            ]);

            // Match VMOS's product to a local SKU where we can, for reporting.
            if ($isNew && ! empty($pad['goodId'])) {
                $instance->sku_id = Sku::where('vmos_good_id', $pad['goodId'])->value('id');
            }

            $instance->save();

            $isNew ? $imported++ : $updated++;
        }

        return back()->with('status', "Import complete — {$imported} new device(s) added to stock, {$updated} existing device(s) refreshed.");
    }
}
