<?php

use App\Modules\Catalog\Http\Controllers\Admin\ProductController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductPhotoController;
use Illuminate\Support\Facades\Route;

Route::get('/produkty', [ProductController::class, 'index'])->name('products.index');
Route::post('/produkty', [ProductController::class, 'store'])->name('products.store');
Route::put('/produkty/strona-glowna', [ProductController::class, 'hero'])->name('products.hero');
Route::put('/produkty/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');
Route::post('/produkty/{product}/przesun', [ProductController::class, 'move'])->whereNumber('product')->name('products.move');
Route::post('/produkty/{product}/widocznosc', [ProductController::class, 'toggle'])->whereNumber('product')->name('products.toggle');

// A photo is found through its product, so a photo of another product gives 404.
Route::post('/produkty/{product}/zdjecia', [ProductPhotoController::class, 'store'])->whereNumber('product')->name('products.photos.store');
Route::post('/produkty/{product}/zdjecia/{media}/przesun', [ProductPhotoController::class, 'move'])->whereNumber(['product', 'media'])->scopeBindings()->name('products.photos.move');
Route::delete('/produkty/{product}/zdjecia/{media}', [ProductPhotoController::class, 'destroy'])->whereNumber(['product', 'media'])->scopeBindings()->name('products.photos.destroy');
