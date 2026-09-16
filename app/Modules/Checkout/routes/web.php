<?php

use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Checkout\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/zamowienie', [CheckoutController::class, 'show'])->name('checkout.index');
Route::post('/zamowienie', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/zamowienie/potwierdzenie', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::get('/odstapienie-od-umowy', [WithdrawalController::class, 'create'])->name('withdrawal.create');
Route::post('/odstapienie-od-umowy', [WithdrawalController::class, 'store'])->name('withdrawal.store');
Route::get('/odstapienie-od-umowy/potwierdzenie', [WithdrawalController::class, 'confirmation'])->name('withdrawal.confirmation');
