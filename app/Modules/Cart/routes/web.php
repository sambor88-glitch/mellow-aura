<?php

use App\Modules\Cart\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

Route::post('/koszyk', [CartController::class, 'store'])->name('cart.store');
Route::patch('/koszyk/{line}', [CartController::class, 'update'])->where('line', 'v\d+(-[0-9a-f]{12})?')->name('cart.update');
Route::delete('/koszyk/{line}', [CartController::class, 'destroy'])->where('line', 'v\d+(-[0-9a-f]{12})?')->name('cart.destroy');
