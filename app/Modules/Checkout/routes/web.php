<?php

use App\Modules\Checkout\Http\Controllers\BlikController;
use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Checkout\Http\Controllers\PieceController;
use App\Modules\Checkout\Http\Controllers\WithdrawalController;
use App\Modules\Checkout\Support\PieceToken;
use Illuminate\Support\Facades\Route;

Route::get('/zamowienie', [CheckoutController::class, 'show'])->name('checkout.index');
Route::post('/zamowienie', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
// Amount vouchers (MA-211): in złoty only, so the English checkout has no such addresses.
Route::post('/zamowienie/voucher', [CheckoutController::class, 'addVoucher'])->middleware('throttle:10,1')->name('checkout.voucher.add');
Route::post('/zamowienie/voucher/usun', [CheckoutController::class, 'removeVoucher'])->name('checkout.voucher.remove');
Route::get('/zamowienie/potwierdzenie', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::post('/zamowienie/dokoncz-platnosc', [CheckoutController::class, 'resume'])->middleware('throttle:10,1')->name('checkout.resume');
Route::get('/zamowienie/blik', [BlikController::class, 'show'])->name('checkout.blik');
Route::post('/zamowienie/blik', [BlikController::class, 'retry'])->middleware('throttle:10,1')->name('checkout.blik.retry');
Route::get('/zamowienie/blik/stan', [BlikController::class, 'status'])->middleware('throttle:60,1')->name('checkout.blik.status');
Route::get('/odstapienie-od-umowy', [WithdrawalController::class, 'create'])->name('withdrawal.create');
Route::post('/odstapienie-od-umowy', [WithdrawalController::class, 'store'])->name('withdrawal.store');
Route::get('/odstapienie-od-umowy/potwierdzenie', [WithdrawalController::class, 'confirmation'])->name('withdrawal.confirmation');
// The QR code in the parcel (MA-234). Polish only in the address; the page speaks the language of the order.
// The throttle keeps anyone from walking through tokens by the thousand.
Route::get('/k/{token}', PieceController::class)->where('token', PieceToken::PATTERN)->middleware('throttle:30,1')->name('piece.show');
