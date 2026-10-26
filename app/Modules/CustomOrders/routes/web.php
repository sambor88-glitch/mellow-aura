<?php

use App\Modules\CustomOrders\Http\Controllers\CustomOrdersController;
use App\Modules\CustomOrders\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/zamowienia-indywidualne', [CustomOrdersController::class, 'show'])->name('custom-orders.index');
// Polish only: the English page has no form (see CustomOrdersController::show).
Route::post('/zamowienia-indywidualne', [CustomOrdersController::class, 'store'])->name('custom-orders.store');
// The payment link Kasia sends from the panel (MA-144). Polish only, like the mails about the idea.
Route::get('/zamowienia-indywidualne/platnosc/potwierdzenie', [PaymentController::class, 'confirmation'])->name('custom-orders.payment.confirmation');
Route::get('/zamowienia-indywidualne/platnosc/{link:token}', [PaymentController::class, 'show'])->where('link', '[A-Za-z0-9]{40}')->name('custom-orders.payment.show');
Route::post('/zamowienia-indywidualne/platnosc/{link:token}', [PaymentController::class, 'store'])->where('link', '[A-Za-z0-9]{40}')->middleware('throttle:10,1')->name('custom-orders.payment.store');
