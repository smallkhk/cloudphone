<?php

namespace App\Http\Controllers;

use App\Models\Sku;
use App\Services\Vmos\VmosRegionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /** Device families per page — the catalogue can run to hundreds of them. */
    protected const GROUPS_PER_PAGE = 12;

    public function __construct(protected VmosRegionCatalog $regionCatalog) {}

    public function index(Request $request)
    {
        $search = $request->string('q')->trim()->value();
        $android = $request->string('android')->trim()->value();
        $duration = $request->string('duration')->trim()->value();
        $model = $request->string('model')->trim()->value();
        $region = $request->string('region')->trim()->value();
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
            'region' => $region,
            'tier' => $tier,
            'androidVersions' => $this->facet('android_version'),
            'models' => $this->facet('config_model'),
            'regions' => $this->facet('default_country_code'),
            // Purchase-time region picker, separate from the "regions" browse
            // filter above (which is only ever populated if an admin sets a
            // per-plan default) — this is VMOS's live supported-country list,
            // and lets the customer choose which region their new device is
            // provisioned in, whatever's picked is passed straight through to
            // createOrder as countryCode.
            'regionOptions' => $this->regionCatalog->options(),
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
            ->when($request->filled('region'), fn ($q) => $q->where('default_country_code', $request->string('region')->value()))
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
