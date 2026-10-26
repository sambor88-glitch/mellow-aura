<?php

use App\Modules\Cart\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

// Line keys: v12 or v12-<text hash> for products, and a letter or word with an optional number or hash for other kinds.
$lineKey = '[a-z]+\d*(-[0-9a-f]{12})?';

Route::post('/koszyk', [CartController::class, 'store'])->name('cart.store');
Route::patch('/koszyk/{line}', [CartController::class, 'update'])->where('line', $lineKey)->name('cart.update');
Route::delete('/koszyk/{line}', [CartController::class, 'destroy'])->where('line', $lineKey)->name('cart.destroy');
