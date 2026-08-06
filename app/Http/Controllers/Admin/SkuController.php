<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sku;
use Illuminate\Http\Request;

class SkuController extends Controller
{
    public function index()
    {
        $skus = Sku::orderBy('android_version')->orderBy('name')->get();

        return view('admin.skus.index', compact('skus'));
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
}
