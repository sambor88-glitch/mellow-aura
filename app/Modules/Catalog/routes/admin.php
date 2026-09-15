<?php

use App\Modules\Catalog\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/produkty', [ProductController::class, 'index'])->name('products.index');
Route::post('/produkty', [ProductController::class, 'store'])->name('products.store');
Route::put('/produkty/strona-glowna', [ProductController::class, 'hero'])->name('products.hero');
Route::put('/produkty/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');
Route::post('/produkty/{product}/przesun', [ProductController::class, 'move'])->whereNumber('product')->name('products.move');
Route::post('/produkty/{product}/widocznosc', [ProductController::class, 'toggle'])->whereNumber('product')->name('products.toggle');
