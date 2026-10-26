<?php

use App\Modules\Gifts\Http\Controllers\BundleController;
use App\Modules\Gifts\Http\Controllers\GiftFinderController;
use App\Modules\Gifts\Http\Controllers\VoucherPageController;
use App\Modules\Gifts\Http\Controllers\VoucherPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/prezenty', GiftFinderController::class)->name('gifts.index');
Route::get('/zestawy-prezentowe', BundleController::class)->name('bundles.index');
Route::get('/voucher-na-warsztaty-ceramiczne', VoucherPageController::class)->name('vouchers.index');

// The code alone is worth money, so the link only works signed, as the confirmation page makes it.
Route::get('/voucher/{voucher:code}', VoucherPdfController::class)->middleware('signed')->name('vouchers.pdf');
