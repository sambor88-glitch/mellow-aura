<?php

use App\Modules\Gifts\Http\Controllers\Admin\GiftChoicesController;
use App\Modules\Gifts\Http\Controllers\Admin\GiftsController;
use App\Modules\Gifts\Http\Controllers\VoucherPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/prezenty', [GiftsController::class, 'edit'])->name('gifts.edit');
Route::post('/prezenty/zestawy', [GiftsController::class, 'storeBundle'])->name('gifts.bundles.store');
Route::put('/prezenty/zestawy/{bundle}', [GiftsController::class, 'updateBundle'])->whereNumber('bundle')->name('gifts.bundles.update');
Route::put('/prezenty/teksty', [GiftsController::class, 'texts'])->name('gifts.texts');
Route::put('/prezenty/vouchery', [GiftsController::class, 'vouchers'])->name('gifts.vouchers');
Route::put('/prezenty/voucher/{voucher}', [GiftsController::class, 'updateVoucher'])->whereNumber('voucher')->name('gifts.voucher.update');
Route::put('/prezenty/zamowienie/{order}/oddaj-na-voucher', [GiftsController::class, 'restoreOrderVouchers'])->whereNumber('order')->name('gifts.order-vouchers.restore');
Route::put('/prezenty/vouchery/opis-warsztatu', [GiftsController::class, 'voucherNotes'])->name('gifts.voucher-notes');
Route::put('/prezenty/szukam-prezentu', [GiftsController::class, 'giftFinder'])->name('gifts.finder');

Route::get('/vouchery/{voucher}', VoucherPdfController::class)->whereNumber('voucher')->name('vouchers.pdf');

// „Podaruj wybór” (MA-235).
Route::get('/karty-wyboru', [GiftChoicesController::class, 'index'])->name('gift-choices.index');
Route::put('/karty-wyboru/ustawienia', [GiftChoicesController::class, 'settings'])->name('gift-choices.settings');
Route::post('/karty-wyboru/{choice}/wyslij', [GiftChoicesController::class, 'resend'])->whereNumber('choice')->name('gift-choices.resend');
Route::post('/karty-wyboru/{choice}/decyzja', [GiftChoicesController::class, 'resolve'])->whereNumber('choice')->name('gift-choices.resolve');
