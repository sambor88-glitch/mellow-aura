<?php

use App\Modules\Catalog\Http\Controllers\Admin\CategoryController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductModelController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductPhotoController;
use Illuminate\Support\Facades\Route;

// The list, a short form for a new product, the card of one product and the settings that apply to all of them (MA-307).
Route::get('/produkty', [ProductController::class, 'index'])->name('products.index');
Route::get('/produkty/nowy', [ProductController::class, 'create'])->name('products.create');
Route::get('/produkty/ustawienia', [ProductController::class, 'settings'])->name('products.settings');
Route::get('/produkty/{product}', [ProductController::class, 'edit'])->whereNumber('product')->name('products.edit');
Route::post('/produkty', [ProductController::class, 'store'])->name('products.store');
Route::put('/produkty/strona-glowna', [ProductController::class, 'hero'])->name('products.hero');
Route::put('/produkty/ceny-w-euro', [ProductController::class, 'euroMarkup'])->name('products.euro-markup');
// Dragging a row on the list sends the whole new order at once (MA-307).
Route::put('/produkty/kolejnosc', [ProductController::class, 'reorder'])->name('products.reorder');
Route::put('/produkty/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');
Route::post('/produkty/{product}/widocznosc', [ProductController::class, 'toggle'])->whereNumber('product')->name('products.toggle');
// − and + on the list save the pieces on the shelf at once; „Duplikuj” makes a hidden copy (MA-307).
Route::put('/produkty/{product}/sztuki', [ProductController::class, 'stock'])->whereNumber('product')->name('products.stock');
Route::post('/produkty/{product}/duplikuj', [ProductController::class, 'duplicate'])->whereNumber('product')->name('products.duplicate');

// A photo is found through its product, so a photo of another product gives 404.
Route::post('/produkty/{product}/zdjecia', [ProductPhotoController::class, 'store'])->whereNumber('product')->name('products.photos.store');
Route::post('/produkty/{product}/zdjecia/{media}/przesun', [ProductPhotoController::class, 'move'])->whereNumber(['product', 'media'])->scopeBindings()->name('products.photos.move');
Route::delete('/produkty/{product}/zdjecia/{media}', [ProductPhotoController::class, 'destroy'])->whereNumber(['product', 'media'])->scopeBindings()->name('products.photos.destroy');

// The 3D model for „Zobacz u siebie” (MA-310): one per product, a new file replaces the old.
Route::post('/produkty/{product}/model-3d', [ProductModelController::class, 'store'])->whereNumber('product')->name('products.model.store');
Route::delete('/produkty/{product}/model-3d', [ProductModelController::class, 'destroy'])->whereNumber('product')->name('products.model.destroy');

Route::get('/kategorie', [CategoryController::class, 'index'])->name('categories.index');
Route::put('/kategorie/skroty', [CategoryController::class, 'shortcuts'])->name('categories.shortcuts');
Route::put('/kategorie/{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
