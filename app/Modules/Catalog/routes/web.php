<?php

use App\Modules\Catalog\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/sklep', ShopController::class)->name('shop.index');
Route::get('/sklep/{category:slug}', ShopController::class)->name('shop.category');
