<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class SkuController extends Controller
{
    protected const TYPES = [Sku::TYPE_CLOUD_PHONE, Sku::TYPE_EMAIL_ACCOUNT, Sku::TYPE_PHONE_NUMBER];

    protected function resolveType(Request $request): string
    {
        $requested = $request->string('type')->value();

        return in_array($requested, self::TYPES, true) ? $requested : Sku::TYPE_CLOUD_PHONE;
    }

    public function index(Request $request)
    {
        $type = $this->resolveType($request);

        $skus = $this->filtered($request, $type)
            ->orderBy('name')
            ->orderBy('duration_minutes')
            ->paginate(40)
            ->withQueryString();

        return view('admin.skus.index', [
            'skus' => $skus,
            'type' => $type,
            'search' => $request->string('q')->trim()->value(),
            'android' => $request->string('android')->trim()->value(),
            'duration' => $request->string('duration')->trim()->value(),
            'model' => $request->string('model')->trim()->value(),
            'tier' => $request->string('tier')->trim()->value(),
            'status' => $request->string('status')->trim()->value(),
            'androidVersions' => $this->androidVersions(),
            'models' => $this->models(),
            'durations' => $this->durations(),
            'stats' => [
                'total' => Sku::where('type', $type)->count(),
                'live' => Sku::where('type', $type)->where('active', true)->count(),
                'matching' => $skus->total(),
            ],
        ]);
    }

    /**
     * Shared filter for the listing and for the bulk actions, so "apply to all
     * matching" really means the rows the admin is looking at — with ~1000
     * plans in the catalogue, acting on the unfiltered table by accident would
     * be painful to undo.
     */
    protected function filtered(Request $request, ?string $type = null): Builder
    {
        $search = $request->string('q')->trim()->value();
        $type ??= $this->resolveType($request);

        return Sku::query()
            ->where('type', $type)
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('config_model', 'like', "%{$search}%")
                ->orWhere('duration_label', 'like', "%{$search}%")))
            ->when($request->filled('android'), fn ($q) => $q->where('android_version', $request->string('android')->value()))
            ->when($request->filled('model'), fn ($q) => $q->where('config_model', $request->string('model')->value()))
            ->when($request->filled('duration'), fn ($q) => $q->where('duration_minutes', (int) $request->string('duration')->value()))
            ->when($request->string('tier')->value() === Sku::TIER_STANDARD, fn ($q) => $q->standardTier())
            ->when($request->string('tier')->value() === Sku::TIER_HIGH_END, fn ($q) => $q->highEndTier())
            ->when($request->string('status')->value() === 'live', fn ($q) => $q->where('active', true))
            ->when($request->string('status')->value() === 'hidden', fn ($q) => $q->where('active', false))
            ->when($request->string('status')->value() === 'unpriced', fn ($q) => $q->where(fn ($w) => $w->whereNull('price')->orWhere('price', 0)));
    }

    /** @return array<int, string> */
    protected function androidVersions(): array
    {
        return Sku::query()
            ->cloudPhones()
            ->whereNotNull('android_version')
            ->where('android_version', '!=', '')
            ->distinct()
            ->orderBy('android_version')
            ->pluck('android_version')
            ->all();
    }

    /** @return array<int, string> */
    protected function models(): array
    {
        return Sku::query()
            ->cloudPhones()
            ->whereNotNull('config_model')
            ->where('config_model', '!=', '')
            ->distinct()
            ->orderBy('config_model')
            ->pluck('config_model')
            ->all();
    }

    /** @return array<int, object{duration_minutes: int, duration_label: string|null}> */
    protected function durations(): array
    {
        return Sku::query()
            ->cloudPhones()
            ->select('duration_minutes', 'duration_label')
            ->groupBy('duration_minutes', 'duration_label')
            ->orderBy('duration_minutes')
            ->get()
            ->unique('duration_minutes')
            ->values()
            ->all();
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

    /** Same as sync(), but for the email-account catalogue. */
    public function syncEmail()
    {
        if (! filled(config('vmos.access_key'))) {
            return back()->with('error', 'Add your VMOS credentials under Settings → VMOS first.');
        }

        try {
            Artisan::call('vmos:sync-email-skus');

            return back()->with('status', trim(Artisan::output()) ?: 'Sync complete.');
        } catch (Throwable $e) {
            return back()->with('error', 'Sync failed: '.$e->getMessage());
        }
    }

    /**
     * Same as sync(), but for the phone-number catalogue. The underlying
     * endpoint is unconfirmed (see CLAUDE.md) — synced SKUs come in hidden,
     * so a failure or garbage response here can't reach a real customer.
     */
    public function syncSms()
    {
        if (! filled(config('vmos.access_key'))) {
            return back()->with('error', 'Add your VMOS credentials under Settings → VMOS first.');
        }

        try {
            Artisan::call('vmos:sync-sms-skus');

            return back()->with('status', trim(Artisan::output()) ?: 'Sync complete.');
        } catch (Throwable $e) {
            return back()->with('error', 'Sync failed: '.$e->getMessage()
                .' — this endpoint is unconfirmed against VMOS, see CLAUDE.md.');
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

        $type = $this->resolveType($request);

        // Honour whatever the admin has filtered the table down to, so it's
        // possible to re-price just one device family. Either way, stay within
        // the catalogue (cloud phones / email accounts) the admin is looking at.
        $query = $request->boolean('scoped') ? $this->filtered($request, $type) : Sku::query()->where('type', $type);

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

    /**
     * Shows or hides every plan matching the current filter in one go. VMOS
     * returns ~1000 plans; curating that down to a sellable shortlist row by
     * row isn't realistic.
     */
    public function bulkStatus(Request $request)
    {
        $request->validate(['active' => ['required', 'boolean']]);

        $active = $request->boolean('active');
        $count = $this->filtered($request)->update(['active' => $active]);

        return back()->with('status', $count.' plan(s) '.($active ? 'are now live on the storefront.' : 'hidden from the storefront.'));
    }
}
