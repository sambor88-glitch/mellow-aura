<?php

use App\Modules\Checkout\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/zamowienie', [CheckoutController::class, 'show'])->name('checkout.index');
Route::post('/zamowienie', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/zamowienie/potwierdzenie', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
