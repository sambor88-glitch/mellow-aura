<?php

use App\Modules\Checkout\Http\Controllers\BlikController;
use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Checkout\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/zamowienie', [CheckoutController::class, 'show'])->name('checkout.index');
Route::post('/zamowienie', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/zamowienie/potwierdzenie', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::post('/zamowienie/dokoncz-platnosc', [CheckoutController::class, 'resume'])->middleware('throttle:10,1')->name('checkout.resume');
Route::get('/zamowienie/blik', [BlikController::class, 'show'])->name('checkout.blik');
Route::post('/zamowienie/blik', [BlikController::class, 'retry'])->middleware('throttle:10,1')->name('checkout.blik.retry');
Route::get('/zamowienie/blik/stan', [BlikController::class, 'status'])->middleware('throttle:60,1')->name('checkout.blik.status');
Route::get('/odstapienie-od-umowy', [WithdrawalController::class, 'create'])->name('withdrawal.create');
Route::post('/odstapienie-od-umowy', [WithdrawalController::class, 'store'])->name('withdrawal.store');
Route::get('/odstapienie-od-umowy/potwierdzenie', [WithdrawalController::class, 'confirmation'])->name('withdrawal.confirmation');
