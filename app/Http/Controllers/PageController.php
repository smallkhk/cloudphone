<?php

namespace App\Http\Controllers;

use App\Models\Sku;

class PageController extends Controller
{
    public function home()
    {
        // Show one representative (cheapest active) plan per device config so the
        // landing page pricing section stays readable.
        $featured = Sku::available()
            ->orderBy('price')
            ->get()
            ->unique('vmos_config_id')
            ->take(3);

        return view('home', compact('featured'));
    }
}
