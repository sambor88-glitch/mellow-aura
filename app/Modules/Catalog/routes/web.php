<?php

use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/sklep', [ShopController::class, 'index'])->name('shop.index');
Route::get('/sklep/{category}', [ShopController::class, 'category'])->name('shop.category');
Route::get('/produkt/{product}', [ProductController::class, 'show'])->name('product.show');
