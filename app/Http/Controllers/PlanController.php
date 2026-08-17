<?php

namespace App\Http\Controllers;

use App\Models\Sku;
use App\Services\Vmos\VmosProxyCatalog;
use App\Services\Vmos\VmosRegionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /** Device families per page — the catalogue can run to hundreds of them. */
    protected const GROUPS_PER_PAGE = 12;

    public function __construct(protected VmosRegionCatalog $regionCatalog, protected VmosProxyCatalog $proxyCatalog) {}

    public function index(Request $request)
    {
        $search = $request->string('q')->trim()->value();
        $android = $request->string('android')->trim()->value();
        $duration = $request->string('duration')->trim()->value();
        $model = $request->string('model')->trim()->value();
        $tier = $request->string('tier')->trim()->value();

        // Paginate device families rather than individual plans: a customer
        // wants to pick a phone first and a duration second, and rendering
        // every duration of every device at once is what made this page slow.
        $groups = $this->filtered($request)
            ->select('name')
            ->selectRaw('MIN(price) as from_price')
            ->selectRaw('MIN(sort_order) as group_sort')
            ->groupBy('name')
            ->orderBy('group_sort')
            ->orderBy('from_price')
            ->paginate(self::GROUPS_PER_PAGE)
            ->withQueryString();

        $names = collect($groups->items())->pluck('name')->all();

        // Second query so each family shows all of its durations, even the ones
        // that didn't set the "from" price.
        $skus = $this->filtered($request)
            ->whereIn('name', $names)
            ->orderBy('duration_minutes')
            ->get()
            ->groupBy('name');

        return view('plans.index', [
            'groups' => $groups,
            'skus' => $skus,
            'search' => $search,
            'android' => $android,
            'duration' => $duration,
            'model' => $model,
            'tier' => $tier,
            'androidVersions' => $this->facet('android_version'),
            'models' => $this->facet('config_model'),
            // Region isn't a property of a plan — any device/duration can be
            // bought in any region — so it's not a browse filter. It's a
            // purchase-time choice: VMOS's live supported-country list, shown
            // on each "Buy now" form and passed straight through to
            // createOrder as countryCode.
            'regionOptions' => $this->regionCatalog->options(),
            // Optional proxy add-on at checkout — bought alongside the device
            // (VMOS residential proxy) or supplied by the customer (their own).
            'proxyProducts' => $this->proxyCatalog->products(),
            'proxyRegions' => $this->proxyCatalog->regions(),
            'durations' => Sku::available()
                ->cloudPhones()
                ->select('duration_minutes', 'duration_label')
                ->groupBy('duration_minutes', 'duration_label')
                ->orderBy('duration_minutes')
                ->get()
                ->unique('duration_minutes')
                ->values(),
            'totalPlans' => Sku::available()->cloudPhones()->count(),
        ]);
    }

    protected function filtered(Request $request): Builder
    {
        $search = $request->string('q')->trim()->value();

        return Sku::available()
            ->cloudPhones()
            ->where('price', '>', 0)
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('config_model', 'like', "%{$search}%")))
            ->when($request->filled('android'), fn ($q) => $q->where('android_version', $request->string('android')->value()))
            ->when($request->filled('duration'), fn ($q) => $q->where('duration_minutes', (int) $request->string('duration')->value()))
            ->when($request->filled('model'), fn ($q) => $q->where('config_model', $request->string('model')->value()))
            ->when($request->string('tier')->value() === Sku::TIER_STANDARD, fn ($q) => $q->standardTier())
            ->when($request->string('tier')->value() === Sku::TIER_HIGH_END, fn ($q) => $q->highEndTier());
    }

    /** @return array<int, string> */
    protected function facet(string $column): array
    {
        return Sku::available()
            ->cloudPhones()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }
}
