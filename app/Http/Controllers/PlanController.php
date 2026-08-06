<?php

namespace App\Http\Controllers;

use App\Models\Sku;

class PlanController extends Controller
{
    public function index()
    {
        $skus = Sku::available()->orderBy('sort_order')->orderBy('price')->get();

        return view('plans.index', compact('skus'));
    }
}
