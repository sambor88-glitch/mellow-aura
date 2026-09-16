<?php

use App\Modules\Gifts\Http\Controllers\Admin\GiftsController;
use App\Modules\Gifts\Http\Controllers\VoucherPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/prezenty', [GiftsController::class, 'edit'])->name('gifts.edit');
Route::post('/prezenty/zestawy', [GiftsController::class, 'storeBundle'])->name('gifts.bundles.store');
Route::put('/prezenty/zestawy/{bundle}', [GiftsController::class, 'updateBundle'])->whereNumber('bundle')->name('gifts.bundles.update');
Route::put('/prezenty/teksty', [GiftsController::class, 'texts'])->name('gifts.texts');
Route::put('/prezenty/vouchery', [GiftsController::class, 'vouchers'])->name('gifts.vouchers');

Route::get('/vouchery/{voucher}', VoucherPdfController::class)->whereNumber('voucher')->name('vouchers.pdf');
