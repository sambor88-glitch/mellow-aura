<?php

use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\Route;

// Temporary week-1 check page: shows that the real offer is in the database.
// Replaced by the home page from the prototype in week 2.
Route::get('/', function () {
    return view('offer-check', [
        'products' => Product::query()
            ->with(['category', 'variants'])
            ->orderBy('sort_order')
            ->get(),
    ]);
})->name('home');
