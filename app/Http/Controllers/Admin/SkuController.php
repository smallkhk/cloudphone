<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class SkuController extends Controller
{
    public function index(Request $request)
    {
        $skus = Sku::query()
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('android_version')
            ->orderBy('name')
            ->orderBy('duration_minutes')
            ->get();

        return view('admin.skus.index', [
            'skus' => $skus,
            'search' => $request->string('q')->trim()->value(),
        ]);
    }

    public function update(Request $request, Sku $sku)
    {
        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $sku->update([
            'price' => $validated['price'],
            'active' => $request->boolean('active'),
            'sort_order' => $validated['sort_order'] ?? $sku->sort_order,
        ]);

        return back()->with('status', "Updated {$sku->name}.");
    }

    /** Pulls the latest catalogue from VMOS without needing shell access. */
    public function sync()
    {
        if (! filled(config('vmos.access_key'))) {
            return back()->with('error', 'Add your VMOS credentials under Settings → VMOS first.');
        }

        try {
            Artisan::call('vmos:sync-skus');

            return back()->with('status', trim(Artisan::output()) ?: 'Sync complete.');
        } catch (Throwable $e) {
            return back()->with('error', 'Sync failed: '.$e->getMessage());
        }
    }

    /** Re-prices every plan at cost + markup%, so pricing can be set in one go. */
    public function bulkMarkup(Request $request)
    {
        $data = $request->validate([
            'markup_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'only_unpriced' => ['sometimes', 'boolean'],
        ]);

        $multiplier = 1 + ((float) $data['markup_percent'] / 100);

        $query = Sku::query();
        if ($request->boolean('only_unpriced')) {
            $query->where(fn ($q) => $q->whereNull('price')->orWhere('price', 0));
        }

        $count = 0;
        foreach ($query->get() as $sku) {
            $sku->update(['price' => round((float) $sku->vmos_cost_price * $multiplier, 2)]);
            $count++;
        }

        return back()->with('status', "Re-priced {$count} plan(s) at cost + {$data['markup_percent']}%.");
    }
}
