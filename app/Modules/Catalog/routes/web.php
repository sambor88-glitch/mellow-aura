<?php

use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ShopController;
use App\Modules\Catalog\Http\Controllers\StockNotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/sklep', ShopController::class)->name('shop.index');
Route::get('/sklep/{category:slug}', ShopController::class)->name('shop.category');
Route::get('/produkt/{product:slug}', ProductController::class)->name('product.show');

// „Napisz do mnie, gdy wróci” on a sold-out size (MA-266).
Route::post('/powiadom-o-dostepnosci', [StockNotificationController::class, 'store'])->middleware('throttle:6,1')->name('stock-notifications.store');
Route::delete('/powiadom-o-dostepnosci/{token}', [StockNotificationController::class, 'destroy'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:20,1')->name('stock-notifications.destroy');
